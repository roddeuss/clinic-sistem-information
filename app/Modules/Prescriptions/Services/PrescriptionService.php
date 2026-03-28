<?php

namespace App\Modules\Prescriptions\Services;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineBranchPrice;
use App\Models\Prescription;
use App\Models\PrescriptionDispense;
use App\Models\PrescriptionInteractionOverride;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Modules\Prescriptions\Exceptions\PrescriptionManagementException;
use App\Services\AuditLogService;
use App\Services\ClinicalBillingService;
use App\Services\ClinicalWorkflowService;
use App\Services\DrugInteractionCheckerService;
use App\Services\MedicineBatchAllocatorService;
use App\Services\ReorderPointService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrescriptionService
{
    private const VISIT_ALLOWED_SORTS = [
        'visit_date',
        'created_at',
    ];

    private const ITEM_ALLOWED_SORTS = [
        'finalized_at',
        'display_name',
        'status',
        'created_at',
    ];

    public function __construct(
        private readonly MedicineBatchAllocatorService $batchAllocator,
        private readonly ClinicalBillingService $clinicalBillingService,
        private readonly ClinicalWorkflowService $clinicalWorkflowService,
        private readonly DrugInteractionCheckerService $drugInteractionCheckerService,
        private readonly ReorderPointService $reorderPointService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $visits = $this->visitTable($filters);
        $dispensingItems = $this->dispensingItemTable($filters);

        return [
            'filters' => $filters,
            'visits' => $visits,
            'dispensingItems' => $dispensingItems,
            'dispensingMetrics' => $this->dispensingMetrics($filters),
            'visitOptions' => $this->visitOptions(),
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'name', 'code']),
            'medicineOptions' => Medicine::query()
                ->with('branchPrices')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'generic_name', 'dosage_form', 'strength', 'base_unit', 'is_compoundable']),
            'abilities' => $this->abilities(),
            'visitSortOptions' => [
                'visit_date' => 'Tanggal visit',
                'created_at' => 'Waktu registrasi',
            ],
            'itemSortOptions' => [
                'finalized_at' => 'Waktu final prescription',
                'display_name' => 'Nama item',
                'status' => 'Status item',
                'created_at' => 'Waktu input item',
            ],
            'perPageOptions' => [10, 25, 50, 100],
        ];
    }

    public function createPrescription(array $payload, User $actor): array
    {
        return $this->transactional(function () use ($payload, $actor): array {
            $visit = $this->resolveManagedVisit((int) $payload['visit_registration_id'], true);
            $existing = Prescription::query()
                ->with($this->prescriptionRelations())
                ->where('visit_registration_id', $visit->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($this->prescriptionMatchesDesiredState($existing, $visit, $payload)) {
                    return ['prescription' => $existing, 'changed' => false];
                }

                throw new PrescriptionManagementException(
                    'Visit ini sudah memiliki prescription. Gunakan update untuk mengubah prescription yang ada.',
                    409,
                    'visit_registration_id',
                );
            }

            $prescription = Prescription::query()->create($this->prescriptionAttributes($visit, $payload));
            $this->syncVisitAfterMutation($visit);
            $prescription = $prescription->fresh($this->prescriptionRelations());

            $this->auditLogService->log(
                module: 'prescription_management',
                action: 'create',
                auditable: $prescription,
                description: sprintf('Prescription %s dibuat oleh %s.', $prescription->getKey(), $actor->email),
                after: $this->prescriptionAuditSnapshot($prescription),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $prescription->visit_registration_id,
                    'branch_id' => $prescription->branch_id,
                ],
            );

            return ['prescription' => $prescription, 'changed' => true];
        });
    }

    public function updatePrescription(Prescription $prescription, array $payload, User $actor): array
    {
        return $this->transactional(function () use ($prescription, $payload, $actor): array {
            $lockedPrescription = $this->lockPrescription($prescription->getKey());

            if ((int) $payload['visit_registration_id'] !== (int) $lockedPrescription->visit_registration_id) {
                throw new PrescriptionManagementException(
                    'Prescription yang sudah tercatat tidak boleh dipindahkan ke visit lain.',
                    409,
                    'visit_registration_id',
                );
            }

            $visit = $this->resolveManagedVisit((int) $lockedPrescription->visit_registration_id, true);

            if ($this->prescriptionMatchesDesiredState($lockedPrescription, $visit, $payload)) {
                return ['prescription' => $lockedPrescription, 'changed' => false];
            }

            $before = $this->prescriptionAuditSnapshot($lockedPrescription);

            $lockedPrescription->fill($this->prescriptionAttributes($visit, $payload));
            $lockedPrescription->save();
            $this->syncVisitAfterMutation($visit);
            $lockedPrescription = $lockedPrescription->fresh($this->prescriptionRelations());

            $this->auditLogService->log(
                module: 'prescription_management',
                action: 'update',
                auditable: $lockedPrescription,
                description: sprintf('Prescription %s diperbarui oleh %s.', $lockedPrescription->getKey(), $actor->email),
                before: $before,
                after: $this->prescriptionAuditSnapshot($lockedPrescription),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $lockedPrescription->visit_registration_id,
                    'branch_id' => $lockedPrescription->branch_id,
                ],
            );

            return ['prescription' => $lockedPrescription, 'changed' => true];
        });
    }

    public function createPrescriptionItem(array $payload, User $actor): array
    {
        return $this->upsertManagedPrescriptionItem($payload, null, $actor);
    }

    public function updatePrescriptionItem(PrescriptionItem $item, array $payload, User $actor): array
    {
        return $this->upsertManagedPrescriptionItem($payload, $item, $actor);
    }

    public function cancelPrescriptionItem(PrescriptionItem $item, User $actor): array
    {
        return $this->transactional(function () use ($item, $actor): array {
            $lockedItem = $this->lockPrescriptionItem($item->getKey());

            if ($lockedItem->isClosedCancelled()) {
                return ['item' => $lockedItem, 'changed' => false];
            }

            if ($lockedItem->dispenses->isNotEmpty()) {
                throw new PrescriptionManagementException(
                    'Item resep yang sudah didispense tidak bisa dibatalkan.',
                    409,
                    'item',
                );
            }

            $before = $this->prescriptionItemAuditSnapshot($lockedItem);

            $lockedItem->update([
                'status' => 'cancelled',
                'closed_remaining_status' => null,
                'closed_remaining_reason' => null,
                'closed_remaining_at' => null,
                'closed_remaining_by_user_id' => null,
            ]);

            $prescription = $lockedItem->prescription->fresh(['items.dispenses']);
            $this->refreshPrescriptionStatus($prescription);
            $this->syncVisitAfterMutation($prescription->visitRegistration()->firstOrFail());
            $lockedItem = $lockedItem->fresh($this->prescriptionItemRelations());

            $this->auditLogService->log(
                module: 'prescription_management',
                action: 'cancel_item',
                auditable: $lockedItem,
                description: sprintf('Item prescription %s dibatalkan oleh %s.', $lockedItem->getKey(), $actor->email),
                before: $before,
                after: $this->prescriptionItemAuditSnapshot($lockedItem),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'prescription_id' => $lockedItem->prescription_id,
                    'branch_id' => $lockedItem->prescription?->branch_id,
                ],
            );

            return ['item' => $lockedItem, 'changed' => true];
        });
    }

    public function finalizePrescription(Prescription $prescription, array $payload, User $actor): array
    {
        return $this->transactional(function () use ($prescription, $payload, $actor): array {
            $lockedPrescription = $this->lockPrescription($prescription->getKey());

            if ($lockedPrescription->items->isEmpty()) {
                throw new PrescriptionManagementException(
                    'Prescription harus punya minimal satu item sebelum difinalkan.',
                    422,
                    'prescription',
                );
            }

            if ($lockedPrescription->isFinalizedWorkflow() && ($payload['notes'] ?? null) === $lockedPrescription->notes) {
                return ['prescription' => $lockedPrescription, 'changed' => false];
            }

            $before = $this->prescriptionAuditSnapshot($lockedPrescription);

            $this->validateInteractionState($lockedPrescription);

            $lockedPrescription->update([
                'status' => 'finalized',
                'notes' => $payload['notes'] ?? $lockedPrescription->notes,
                'finalized_at' => $lockedPrescription->finalized_at ?? now(),
                'finalized_by_user_id' => $lockedPrescription->finalized_by_user_id ?? $actor->getKey(),
            ]);

            $this->refreshPrescriptionStatus($lockedPrescription->fresh(['items.dispenses']));
            $this->syncVisitAfterMutation($lockedPrescription->visitRegistration()->firstOrFail());
            $lockedPrescription = $lockedPrescription->fresh($this->prescriptionRelations());

            $this->auditLogService->log(
                module: 'prescription_management',
                action: 'finalize',
                auditable: $lockedPrescription,
                description: sprintf('Prescription %s difinalkan oleh %s.', $lockedPrescription->getKey(), $actor->email),
                before: $before,
                after: $this->prescriptionAuditSnapshot($lockedPrescription),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $lockedPrescription->visit_registration_id,
                    'branch_id' => $lockedPrescription->branch_id,
                ],
            );

            return ['prescription' => $lockedPrescription, 'changed' => true];
        });
    }

    public function dispenseItem(PrescriptionItem $item, array $payload, User $actor): PrescriptionDispense
    {
        return $this->transactional(function () use ($item, $payload, $actor): PrescriptionDispense {
            $lockedItem = $this->lockPrescriptionItem($item->getKey());

            if ($lockedItem->isExternal() || $lockedItem->isClosedExternally() || $lockedItem->isClosedCancelled()) {
                throw new PrescriptionManagementException(
                    'Item resep luar atau cancelled tidak bisa didispense dari stok klinik.',
                    409,
                    'dispense',
                );
            }

            if ($lockedItem->prescription?->isDraft()) {
                throw new PrescriptionManagementException(
                    'Prescription masih draft. Finalkan dulu sebelum dispensing.',
                    409,
                    'dispense',
                );
            }

            $this->validateDispenseInteractionState($lockedItem);

            $remaining = $this->remainingQuantity($lockedItem);
            $dispenseQuantity = (float) $payload['quantity_dispensed'];

            if ($dispenseQuantity > $remaining) {
                throw new PrescriptionManagementException(
                    'Jumlah dispense melebihi sisa quantity yang belum dilayani.',
                    422,
                    'quantity_dispensed',
                );
            }

            $visit = $lockedItem->prescription->visitRegistration;
            $unitPrice = $this->resolveDispensePrice($lockedItem, $visit->branch_id, $payload['unit_price'] ?? null);

            $dispense = PrescriptionDispense::query()->create([
                'prescription_item_id' => $lockedItem->id,
                'visit_registration_id' => $visit->id,
                'branch_id' => $visit->branch_id,
                'dispensed_by_user_id' => $actor->getKey(),
                'quantity_dispensed' => $dispenseQuantity,
                'unit_price' => $unitPrice,
                'subtotal' => $dispenseQuantity * $unitPrice,
                'dispensed_at' => now(),
                'notes' => $payload['notes'] ?? null,
            ]);

            $this->allocateDispenseBatches($dispense, $lockedItem, $visit->branch_id, $dispenseQuantity);

            $dispensedTotal = $this->dispensedQuantity($lockedItem->fresh('dispenses'));

            $lockedItem->update([
                'status' => $dispensedTotal >= (float) $lockedItem->quantity_prescribed ? 'dispensed' : 'partial',
                'closed_remaining_status' => null,
                'closed_remaining_reason' => null,
                'closed_remaining_at' => null,
                'closed_remaining_by_user_id' => null,
            ]);

            $this->refreshReorderForItem($lockedItem->fresh([
                'medicine:id,code,name,generic_name,active_ingredients,therapeutic_class',
                'compoundIngredients.medicine:id,code,name,generic_name,active_ingredients,therapeutic_class',
                'prescription.visitRegistration:id,branch_id',
            ]));

            $prescription = $lockedItem->prescription->fresh(['items.dispenses']);
            $this->refreshPrescriptionStatus($prescription);
            $this->syncVisitAfterMutation($visit);
            $dispense = $dispense->fresh($this->dispenseRelations());

            $this->auditLogService->log(
                module: 'prescription_management',
                action: 'dispense',
                auditable: $dispense,
                description: sprintf('Dispense %s dibuat oleh %s.', $dispense->getKey(), $actor->email),
                after: $this->dispenseAuditSnapshot($dispense),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'prescription_id' => $lockedItem->prescription_id,
                    'prescription_item_id' => $lockedItem->getKey(),
                    'visit_registration_id' => $visit->getKey(),
                    'branch_id' => $visit->branch_id,
                ],
            );

            return $dispense;
        });
    }

    public function closeRemainingItem(PrescriptionItem $item, array $payload, User $actor): array
    {
        return $this->transactional(function () use ($item, $payload, $actor): array {
            $lockedItem = $this->lockPrescriptionItem($item->getKey());

            if ($lockedItem->isExternal()) {
                throw new PrescriptionManagementException(
                    'Resep luar tidak membutuhkan penutupan dispensing in-house.',
                    409,
                    'item',
                );
            }

            if (
                $lockedItem->closed_remaining_status === $payload['closure_status']
                && $lockedItem->closed_remaining_reason === $payload['closure_reason']
            ) {
                return ['item' => $lockedItem, 'changed' => false];
            }

            if ($lockedItem->isClosedExternally() || $lockedItem->isClosedCancelled() || $lockedItem->status === 'dispensed') {
                throw new PrescriptionManagementException(
                    'Item ini sudah tidak punya sisa fulfillment yang perlu ditutup.',
                    409,
                    'item',
                );
            }

            $remaining = $this->remainingQuantity($lockedItem);

            if ($remaining <= 0) {
                throw new PrescriptionManagementException(
                    'Tidak ada sisa quantity untuk ditutup.',
                    409,
                    'item',
                );
            }

            $hasDispense = $this->dispensedQuantity($lockedItem) > 0;
            $targetStatus = match ($payload['closure_status']) {
                'external' => $hasDispense ? 'partial_external' : 'external',
                'cancelled' => $hasDispense ? 'partial_cancelled' : 'cancelled',
                default => throw new PrescriptionManagementException('Status penutupan tidak dikenali.', 422, 'closure_status'),
            };

            $before = $this->prescriptionItemAuditSnapshot($lockedItem);

            $lockedItem->update([
                'status' => $targetStatus,
                'closed_remaining_status' => $payload['closure_status'],
                'closed_remaining_reason' => $payload['closure_reason'],
                'closed_remaining_at' => now(),
                'closed_remaining_by_user_id' => $actor->getKey(),
            ]);

            $prescription = $lockedItem->prescription->fresh(['items.dispenses']);
            $this->refreshPrescriptionStatus($prescription);
            $this->syncVisitAfterMutation($lockedItem->prescription->visitRegistration()->firstOrFail());
            $lockedItem = $lockedItem->fresh($this->prescriptionItemRelations());

            $this->auditLogService->log(
                module: 'prescription_management',
                action: 'close_remaining',
                auditable: $lockedItem,
                description: sprintf('Sisa fulfillment item prescription %s ditutup oleh %s.', $lockedItem->getKey(), $actor->email),
                before: $before,
                after: $this->prescriptionItemAuditSnapshot($lockedItem),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'prescription_id' => $lockedItem->prescription_id,
                    'branch_id' => $lockedItem->prescription?->branch_id,
                ],
            );

            return ['item' => $lockedItem, 'changed' => true];
        });
    }

    public function overrideMajorInteractions(PrescriptionItem $item, array $payload, User $actor): array
    {
        return $this->transactional(function () use ($item, $payload, $actor): array {
            $lockedItem = $this->lockPrescriptionItem($item->getKey());
            $lockedItem->loadMissing(['prescription.interactionOverrides']);

            $pendingAlerts = collect($this->drugInteractionCheckerService->alertsForItem($lockedItem))
                ->filter(fn (array $alert): bool => ($alert['requires_override'] ?? false) === true && ($alert['overridden'] ?? false) === false)
                ->values();

            if ($pendingAlerts->isEmpty()) {
                $hasSameReason = $lockedItem->interactionOverrides
                    ->contains(fn (PrescriptionInteractionOverride $override): bool => $override->reason === $payload['override_reason']);

                if ($hasSameReason) {
                    return ['item' => $lockedItem, 'changed' => false];
                }

                throw new PrescriptionManagementException(
                    'Tidak ada alert major yang perlu dioverride untuk item ini.',
                    409,
                    'override_reason',
                );
            }

            $before = $this->prescriptionItemAuditSnapshot($lockedItem);

            $this->drugInteractionCheckerService->overrideMajorAlerts($lockedItem, $payload['override_reason'], $actor);
            $lockedItem = $lockedItem->fresh($this->prescriptionItemRelations());

            $this->auditLogService->log(
                module: 'prescription_management',
                action: 'override_interaction',
                auditable: $lockedItem,
                description: sprintf('Override interaksi item prescription %s dilakukan oleh %s.', $lockedItem->getKey(), $actor->email),
                before: $before,
                after: $this->prescriptionItemAuditSnapshot($lockedItem),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'prescription_id' => $lockedItem->prescription_id,
                    'branch_id' => $lockedItem->prescription?->branch_id,
                ],
            );

            return ['item' => $lockedItem, 'changed' => true];
        });
    }

    public function prescriptionPayload(Prescription $prescription): array
    {
        $prescription->loadMissing($this->prescriptionRelations());

        return [
            'id' => $prescription->getKey(),
            'visit_registration_id' => $prescription->visit_registration_id,
            'patient_id' => $prescription->patient_id,
            'patient_name' => $prescription->patient?->full_name,
            'branch_id' => $prescription->branch_id,
            'branch_code' => $prescription->branch?->code,
            'section_id' => $prescription->section_id,
            'section_name' => $prescription->section?->name,
            'doctor_id' => $prescription->doctor_id,
            'doctor_name' => $prescription->doctor?->displayName(),
            'status' => $prescription->status,
            'notes' => $prescription->notes,
            'finalized_at' => $prescription->finalized_at?->toIso8601String(),
            'items_count' => $prescription->items->count(),
        ];
    }

    public function prescriptionItemPayload(PrescriptionItem $item): array
    {
        $item->loadMissing($this->prescriptionItemRelations());

        return [
            'id' => $item->getKey(),
            'prescription_id' => $item->prescription_id,
            'prescription_status' => $item->prescription?->status,
            'item_type' => $item->item_type,
            'medicine_id' => $item->medicine_id,
            'medicine_name' => $item->medicine?->name,
            'display_name' => $item->display_name,
            'route' => $item->route,
            'dose_amount' => $item->dose_amount !== null ? (float) $item->dose_amount : null,
            'dose_unit' => $item->dose_unit,
            'frequency' => $item->frequency,
            'duration_days' => $item->duration_days,
            'instruction' => $item->instruction,
            'quantity_prescribed' => (float) $item->quantity_prescribed,
            'dispense_unit' => $item->dispense_unit,
            'weight_snapshot_kg' => $item->weight_snapshot_kg !== null ? (float) $item->weight_snapshot_kg : null,
            'status' => $item->status,
            'notes' => $item->notes,
            'closed_remaining_status' => $item->closed_remaining_status,
            'closed_remaining_reason' => $item->closed_remaining_reason,
            'closed_remaining_at' => $item->closed_remaining_at?->toIso8601String(),
            'dispensed_quantity' => $this->dispensedQuantity($item),
            'remaining_quantity' => $this->remainingQuantity($item),
            'compound_ingredients' => $item->compoundIngredients->map(fn ($ingredient): array => [
                'id' => $ingredient->getKey(),
                'medicine_id' => $ingredient->medicine_id,
                'medicine_name' => $ingredient->medicine?->name,
                'quantity_required' => (float) $ingredient->quantity_required,
                'unit' => $ingredient->unit,
            ])->values()->all(),
            'safety_alerts' => $this->buildSafetyAlerts($item),
            'dispensing_preview' => $this->buildDispensingPreview($item),
        ];
    }

    public function dispensePayload(PrescriptionDispense $dispense): array
    {
        $dispense->loadMissing($this->dispenseRelations());

        return [
            'id' => $dispense->getKey(),
            'prescription_item_id' => $dispense->prescription_item_id,
            'visit_registration_id' => $dispense->visit_registration_id,
            'branch_id' => $dispense->branch_id,
            'quantity_dispensed' => (float) $dispense->quantity_dispensed,
            'unit_price' => (float) $dispense->unit_price,
            'subtotal' => (float) $dispense->subtotal,
            'dispensed_at' => $dispense->dispensed_at?->toIso8601String(),
            'notes' => $dispense->notes,
            'dispensed_by' => $dispense->dispensedBy?->name,
            'batch_usages' => $dispense->batchUsages->map(fn ($usage): array => [
                'medicine_batch_id' => $usage->medicine_batch_id,
                'batch_number' => $usage->medicineBatch?->batch_number,
                'expired_at' => $usage->medicineBatch?->expired_at?->toDateString(),
                'quantity_used' => (float) $usage->quantity_used,
            ])->values()->all(),
        ];
    }

    public function visitPayload(VisitRegistration $visit): array
    {
        $visit->loadMissing($this->visitRelations());

        return [
            'id' => $visit->getKey(),
            'patient_name' => $visit->patient?->full_name,
            'patient_phone' => $visit->patient?->phone,
            'medical_record_no' => $visit->patientBranchRecord?->medical_record_no,
            'branch_id' => $visit->branch_id,
            'branch_code' => $visit->branch?->code,
            'section_name' => $visit->section?->name,
            'doctor_name' => $visit->medicalRecord?->doctor?->displayName() ?? $visit->doctor?->displayName(),
            'visit_date' => $visit->visit_date?->toDateString(),
            'care_stage' => $visit->care_stage,
            'prescription' => $visit->prescription ? $this->prescriptionPayload($visit->prescription) : null,
        ];
    }

    public function getLabelData(PrescriptionDispense $dispense): array
    {
        $dispense->loadMissing($this->dispenseRelations());

        return [
            'dispense' => $dispense,
            'item' => $dispense->prescriptionItem,
            'visit' => $dispense->prescriptionItem?->prescription?->visitRegistration,
        ];
    }

    private function transactional(callable $callback): mixed
    {
        try {
            return DB::transaction($callback);
        } catch (PrescriptionManagementException $exception) {
            throw $exception;
        } catch (ValidationException $exception) {
            throw $this->mapValidationException($exception);
        }
    }

    private function mapValidationException(ValidationException $exception): PrescriptionManagementException
    {
        $errors = $exception->errors();
        $key = (string) (array_key_first($errors) ?? 'prescription');
        $message = (string) ($errors[$key][0] ?? $exception->getMessage());

        return new PrescriptionManagementException($message, 422, $key);
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'search' => trim((string) ($filters['search'] ?? '')),
            'branch' => (string) ($filters['branch'] ?? ''),
            'status' => (string) ($filters['status'] ?? ''),
            'date' => (string) ($filters['date'] ?? now()->toDateString()),
            'visit_sort_by' => (string) ($filters['visit_sort_by'] ?? 'visit_date'),
            'visit_sort_direction' => (string) ($filters['visit_sort_direction'] ?? 'desc'),
            'visit_per_page' => (int) ($filters['visit_per_page'] ?? 10),
            'item_sort_by' => (string) ($filters['item_sort_by'] ?? 'finalized_at'),
            'item_sort_direction' => (string) ($filters['item_sort_direction'] ?? 'desc'),
            'item_per_page' => (int) ($filters['item_per_page'] ?? 10),
        ];
    }

    private function resolveManagedVisit(int $visitRegistrationId, bool $lock = false): VisitRegistration
    {
        $query = VisitRegistration::query()
            ->with([
                'medicalRecord:id,visit_registration_id,status,doctor_id',
                'doctor:id,full_name,title_prefix,title_suffix',
            ]);

        if ($lock) {
            $query->lockForUpdate();
        }

        $visit = $query->find($visitRegistrationId);

        if ($visit === null) {
            throw new PrescriptionManagementException('Visit registration tidak ditemukan.', 404, 'visit_registration_id');
        }

        if (! $visit->medicalRecord) {
            throw new PrescriptionManagementException(
                'Prescription hanya bisa dibuat setelah ada SOAP/medical record visit.',
                422,
                'visit_registration_id',
            );
        }

        if (in_array($visit->care_stage, ['cancelled', 'completed'], true)) {
            throw new PrescriptionManagementException(
                'Visit ini sudah selesai atau dibatalkan.',
                409,
                'visit_registration_id',
            );
        }

        return $visit;
    }

    private function lockPrescription(int $prescriptionId): Prescription
    {
        $prescription = Prescription::query()
            ->with($this->prescriptionRelations())
            ->lockForUpdate()
            ->find($prescriptionId);

        if ($prescription === null) {
            throw new PrescriptionManagementException('Prescription tidak ditemukan.', 404, 'prescription');
        }

        return $prescription;
    }

    private function lockPrescriptionItem(int $itemId): PrescriptionItem
    {
        $item = PrescriptionItem::query()
            ->with($this->prescriptionItemRelations())
            ->lockForUpdate()
            ->find($itemId);

        if ($item === null) {
            throw new PrescriptionManagementException('Item prescription tidak ditemukan.', 404, 'item');
        }

        return $item;
    }

    private function prescriptionAttributes(VisitRegistration $visit, array $payload): array
    {
        return [
            'visit_registration_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'doctor_id' => $visit->medicalRecord?->doctor_id ?? $visit->doctor_id,
            'notes' => $payload['notes'] ?? null,
            'status' => 'draft',
        ];
    }

    private function prescriptionMatchesDesiredState(Prescription $prescription, VisitRegistration $visit, array $payload): bool
    {
        $attributes = $this->prescriptionAttributes($visit, $payload);

        return (int) $prescription->visit_registration_id === (int) $attributes['visit_registration_id']
            && (int) $prescription->patient_id === (int) $attributes['patient_id']
            && (int) $prescription->branch_id === (int) $attributes['branch_id']
            && (int) $prescription->section_id === (int) $attributes['section_id']
            && (int) $prescription->doctor_id === (int) $attributes['doctor_id']
            && $prescription->notes === $attributes['notes'];
    }

    private function upsertManagedPrescriptionItem(array $payload, ?PrescriptionItem $item, User $actor): array
    {
        return $this->transactional(function () use ($payload, $item, $actor): array {
            $prescription = $this->lockPrescription((int) $payload['prescription_id']);
            $visit = $this->resolveManagedVisit((int) $prescription->visit_registration_id, true);

            if ($item !== null && (int) $item->prescription_id !== (int) $prescription->getKey()) {
                throw new PrescriptionManagementException(
                    'Item prescription tidak sesuai dengan prescription yang dipilih.',
                    409,
                    'prescription_id',
                );
            }

            if ($item !== null) {
                $item = $this->lockPrescriptionItem($item->getKey());
            }

            if ($item !== null && $item->dispenses->isNotEmpty()) {
                throw new PrescriptionManagementException(
                    'Item resep yang sudah didispense tidak bisa diubah langsung.',
                    409,
                    'item',
                );
            }

            if ($prescription->status === 'dispensed') {
                throw new PrescriptionManagementException(
                    'Prescription yang sudah selesai didispense tidak bisa diubah lagi dari halaman ini.',
                    409,
                    'prescription',
                );
            }

            $medicine = filled($payload['medicine_id'] ?? null)
                ? Medicine::query()->find($payload['medicine_id'])
                : null;

            if (filled($payload['medicine_id'] ?? null) && $medicine === null) {
                throw new PrescriptionManagementException('Medicine yang dipilih tidak ditemukan.', 404, 'medicine_id');
            }

            $displayName = $this->resolveDisplayName($payload['item_type'], $medicine, $payload);
            $normalizedIngredients = $this->normalizedCompoundIngredientsRows($payload);
            $desiredAttributes = [
                'prescription_id' => $prescription->id,
                'medicine_id' => $medicine?->id,
                'item_type' => $payload['item_type'],
                'display_name' => $displayName,
                'route' => $payload['route'],
                'dose_amount' => $payload['dose_amount'],
                'dose_unit' => $payload['dose_unit'],
                'frequency' => $payload['frequency'],
                'duration_days' => $payload['duration_days'],
                'instruction' => $payload['instruction'],
                'quantity_prescribed' => $payload['quantity_prescribed'],
                'dispense_unit' => $payload['dispense_unit'] ?: ($medicine?->base_unit),
                'weight_snapshot_kg' => $payload['weight_snapshot_kg'],
                'status' => $this->resolveItemStatus($payload['item_type'], $payload['status'] ?? null),
                'notes' => $payload['notes'],
                'sort_order' => $item?->sort_order ?? (($prescription->items()->max('sort_order') ?? 0) + 10),
            ];

            if ($item !== null && $this->itemMatchesDesiredState($item, $desiredAttributes, $normalizedIngredients)) {
                return ['item' => $item, 'changed' => false];
            }

            $entity = $item ?: new PrescriptionItem();
            $before = $item ? $this->prescriptionItemAuditSnapshot($entity) : [];

            $entity->fill($desiredAttributes);
            $entity->save();

            $entity->compoundIngredients()->delete();

            if ($payload['item_type'] === 'compound') {
                $entity->compoundIngredients()->createMany(
                    collect($normalizedIngredients)
                        ->map(fn (array $row): array => [
                            'medicine_id' => $row['medicine_id'],
                            'quantity_required' => $row['quantity_required'],
                            'unit' => $row['unit'],
                        ])
                        ->all()
                );
            }

            $prescription = $prescription->fresh(['items.dispenses']);
            $this->refreshPrescriptionStatus($prescription);
            $this->syncVisitAfterMutation($visit);
            $entity = $entity->fresh($this->prescriptionItemRelations());

            $this->auditLogService->log(
                module: 'prescription_management',
                action: $item ? 'update_item' : 'create_item',
                auditable: $entity,
                description: sprintf(
                    'Item prescription %s %s oleh %s.',
                    $entity->getKey(),
                    $item ? 'diperbarui' : 'dibuat',
                    $actor->email
                ),
                before: $before,
                after: $this->prescriptionItemAuditSnapshot($entity),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'prescription_id' => $entity->prescription_id,
                    'branch_id' => $entity->prescription?->branch_id,
                ],
            );

            return ['item' => $entity, 'changed' => true];
        });
    }

    private function itemMatchesDesiredState(PrescriptionItem $item, array $attributes, array $normalizedIngredients): bool
    {
        $currentIngredients = $item->compoundIngredients
            ->map(fn ($ingredient): array => [
                'medicine_id' => (int) $ingredient->medicine_id,
                'quantity_required' => (float) $ingredient->quantity_required,
                'unit' => $ingredient->unit,
            ])
            ->values()
            ->all();

        return (int) $item->prescription_id === (int) $attributes['prescription_id']
            && (int) ($item->medicine_id ?? 0) === (int) ($attributes['medicine_id'] ?? 0)
            && $item->item_type === $attributes['item_type']
            && $item->display_name === $attributes['display_name']
            && $item->route === $attributes['route']
            && (float) ($item->dose_amount ?? 0) === (float) ($attributes['dose_amount'] ?? 0)
            && $item->dose_unit === $attributes['dose_unit']
            && $item->frequency === $attributes['frequency']
            && (int) ($item->duration_days ?? 0) === (int) ($attributes['duration_days'] ?? 0)
            && $item->instruction === $attributes['instruction']
            && (float) $item->quantity_prescribed === (float) $attributes['quantity_prescribed']
            && $item->dispense_unit === $attributes['dispense_unit']
            && (float) ($item->weight_snapshot_kg ?? 0) === (float) ($attributes['weight_snapshot_kg'] ?? 0)
            && $item->status === $attributes['status']
            && $item->notes === $attributes['notes']
            && $currentIngredients === $normalizedIngredients;
    }

    private function normalizedCompoundIngredientsRows(array $payload): array
    {
        $rows = collect($payload['compound_ingredients'] ?? [])
            ->map(fn (array $row): array => [
                'medicine_id' => (int) $row['medicine_id'],
                'quantity_required' => (float) $row['quantity_required'],
                'unit' => $row['unit'] ?: null,
            ])
            ->values()
            ->all();

        if ($payload['item_type'] === 'compound' && $rows === []) {
            throw new PrescriptionManagementException(
                'Compound capsule memerlukan minimal satu ingredient.',
                422,
                'compound_ingredients',
            );
        }

        return $rows;
    }

    private function resolveDisplayName(string $itemType, ?Medicine $medicine, array $payload): string
    {
        if (in_array($itemType, ['in_house', 'external'], true) && ! $medicine) {
            throw new PrescriptionManagementException(
                'Medicine wajib dipilih untuk resep in-house atau resep luar.',
                422,
                'medicine_id',
            );
        }

        $displayName = $itemType === 'compound'
            ? trim((string) ($payload['display_name'] ?? ''))
            : $this->medicineLabel($medicine);

        if ($displayName === '') {
            throw new PrescriptionManagementException('Nama compound wajib diisi.', 422, 'display_name');
        }

        return $displayName;
    }

    private function validateInteractionState(Prescription $prescription): void
    {
        try {
            $this->drugInteractionCheckerService->validatePrescription($prescription);
        } catch (ValidationException $exception) {
            throw $this->mapValidationException($exception);
        }
    }

    private function validateDispenseInteractionState(PrescriptionItem $item): void
    {
        try {
            $this->drugInteractionCheckerService->validateDispense($item);
        } catch (ValidationException $exception) {
            throw $this->mapValidationException($exception);
        }
    }

    private function allocateDispenseBatches(
        PrescriptionDispense $dispense,
        PrescriptionItem $item,
        int $branchId,
        float $dispenseQuantity,
    ): void {
        if ($item->isCompound()) {
            $ratio = $dispenseQuantity / max(1, (float) $item->quantity_prescribed);

            foreach ($item->compoundIngredients as $ingredient) {
                $required = (float) $ingredient->quantity_required * $ratio;
                $allocations = $this->allocateBatchesOrFail($ingredient->medicine, $branchId, $required);

                foreach ($allocations as $allocation) {
                    $allocation['batch']->decrement('quantity_available', $allocation['quantity']);

                    $dispense->batchUsages()->create([
                        'medicine_batch_id' => $allocation['batch']->id,
                        'quantity_used' => $allocation['quantity'],
                        'purchase_cost_snapshot' => $allocation['batch']->purchase_cost,
                    ]);
                }
            }

            return;
        }

        $allocations = $this->allocateBatchesOrFail($item->medicine, $branchId, $dispenseQuantity);

        foreach ($allocations as $allocation) {
            $allocation['batch']->decrement('quantity_available', $allocation['quantity']);

            $dispense->batchUsages()->create([
                'medicine_batch_id' => $allocation['batch']->id,
                'quantity_used' => $allocation['quantity'],
                'purchase_cost_snapshot' => $allocation['batch']->purchase_cost,
            ]);
        }
    }

    private function allocateBatchesOrFail(Medicine $medicine, int $branchId, float $requiredQuantity): Collection
    {
        try {
            return $this->batchAllocator->allocate($medicine, $branchId, $requiredQuantity);
        } catch (ValidationException $exception) {
            throw $this->mapValidationException($exception);
        }
    }

    private function visitTable(array $filters): LengthAwarePaginator
    {
        return VisitRegistration::query()
            ->with($this->visitRelations())
            ->whereHas('medicalRecord')
            ->whereNotIn('care_stage', ['cancelled', 'completed'])
            ->searchForManagement($filters['search'])
            ->when($filters['branch'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->when($filters['date'] !== '', fn (Builder $query) => $query->whereDate('visit_date', $filters['date']))
            ->when($filters['status'] !== '', function (Builder $query) use ($filters): void {
                match ($filters['status']) {
                    'none' => $query->whereDoesntHave('prescription'),
                    default => $query->whereHas('prescription', fn (Builder $prescriptionQuery) => $prescriptionQuery->where('status', $filters['status'])),
                };
            })
            ->orderByAllowed($filters['visit_sort_by'], $filters['visit_sort_direction'], self::VISIT_ALLOWED_SORTS)
            ->paginate($filters['visit_per_page'], ['*'], 'visit_page')
            ->withQueryString();
    }

    private function visitOptions(): Collection
    {
        return VisitRegistration::query()
            ->with($this->visitRelations())
            ->whereHas('medicalRecord')
            ->whereNotIn('care_stage', ['cancelled', 'completed'])
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->limit(150)
            ->get([
                'id',
                'patient_id',
                'patient_branch_record_id',
                'branch_id',
                'section_id',
                'doctor_id',
                'visit_date',
                'care_stage',
            ]);
    }

    private function dispensingMetrics(array $filters): array
    {
        $baseVisitQuery = VisitRegistration::query()
            ->whereHas('medicalRecord')
            ->whereNotIn('care_stage', ['cancelled', 'completed'])
            ->when($filters['branch'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->when($filters['date'] !== '', fn (Builder $query) => $query->whereDate('visit_date', $filters['date']));

        $baseItemQuery = PrescriptionItem::query()
            ->whereHas('prescription.visitRegistration', function (Builder $query) use ($filters): void {
                $query
                    ->whereNotIn('care_stage', ['cancelled', 'completed'])
                    ->when($filters['branch'] !== '', fn (Builder $builder) => $builder->where('branch_id', $filters['branch']))
                    ->when($filters['date'] !== '', fn (Builder $builder) => $builder->whereDate('visit_date', $filters['date']));
            });

        return [
            'visits_ready' => (clone $baseVisitQuery)->count(),
            'without_prescription' => (clone $baseVisitQuery)->whereDoesntHave('prescription')->count(),
            'pending_dispense_items' => (clone $baseItemQuery)
                ->whereIn('status', ['pending', 'partial'])
                ->whereHas('prescription', fn (Builder $query) => $query->whereIn('status', ['finalized', 'partial_dispensed']))
            ->count(),
            'partial_dispensed_prescriptions' => Prescription::query()
                ->where('status', 'partial_dispensed')
                ->filterDate($filters['date'])
                ->filterBranch($filters['branch'])
                ->count(),
            'dispensed_prescriptions' => Prescription::query()
                ->where('status', 'dispensed')
                ->filterDate($filters['date'])
                ->filterBranch($filters['branch'])
                ->count(),
        ];
    }

    private function dispensingItemTable(array $filters): LengthAwarePaginator
    {
        $query = PrescriptionItem::query()
            ->with($this->prescriptionItemRelations())
            ->whereHas('prescription.visitRegistration', function (Builder $query) use ($filters): void {
                $query
                    ->whereNotIn('care_stage', ['cancelled', 'completed'])
                    ->when($filters['branch'] !== '', fn (Builder $builder) => $builder->where('branch_id', $filters['branch']))
                    ->when($filters['date'] !== '', fn (Builder $builder) => $builder->whereDate('visit_date', $filters['date']));
            })
            ->searchForManagement($filters['search'])
            ->when($filters['status'] === 'none', fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->filterPrescriptionStatus($filters['status'])
            ->orderByAllowed($filters['item_sort_by'], $filters['item_sort_direction'], self::ITEM_ALLOWED_SORTS);

        $paginator = $query
            ->paginate($filters['item_per_page'], ['*'], 'dispense_page')
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(function (PrescriptionItem $item): PrescriptionItem {
                $item->setAttribute('dispensed_quantity', $this->dispensedQuantity($item));
                $item->setAttribute('remaining_quantity', $this->remainingQuantity($item));
                $item->setAttribute('dispensing_preview', $this->buildDispensingPreview($item));
                $item->setAttribute('safety_alerts', $this->buildSafetyAlerts($item));
                $item->setAttribute('latest_dispense', $item->dispenses->first());

                return $item;
            })
        );

        return $paginator;
    }

    private function resolveItemStatus(string $itemType, ?string $requestedStatus): string
    {
        if ($itemType === 'external') {
            return 'external';
        }

        return $requestedStatus === 'cancelled' ? 'cancelled' : 'pending';
    }

    private function medicineLabel(?Medicine $medicine): string
    {
        if (! $medicine) {
            return '';
        }

        return collect([
            $medicine->name,
            $medicine->strength,
        ])->filter()->implode(' ');
    }

    private function buildDispensingPreview(PrescriptionItem $item): array
    {
        $visit = $item->prescription?->visitRegistration;
        $branchId = $visit?->branch_id;
        $remaining = $this->remainingQuantity($item);

        if (! $visit || ! $branchId) {
            return [
                'type' => 'unknown',
                'summary' => 'Visit branch belum tersedia.',
            ];
        }

        if ($item->isExternal()) {
            return [
                'type' => 'external',
                'summary' => 'Resep luar. Tidak memotong stok dan tidak masuk dispensing in-house.',
            ];
        }

        if ($item->isCompound()) {
            $ratio = $remaining / max(1, (float) $item->quantity_prescribed);
            $ingredients = $item->compoundIngredients->map(function ($ingredient) use ($branchId, $ratio): array {
                $batches = $this->availableBatchesForMedicine((int) $ingredient->medicine_id, $branchId);
                $requiredForRemaining = (float) $ingredient->quantity_required * $ratio;

                return [
                    'label' => trim(($ingredient->medicine?->code ?? '-') . ' - ' . ($ingredient->medicine?->name ?? '-')),
                    'required_for_remaining' => $requiredForRemaining,
                    'available_quantity' => (float) $batches->sum('quantity_available'),
                    'nearest_batch' => $batches->first()?->batch_number,
                    'nearest_expiry' => $batches->first()?->expired_at?->format('d M Y'),
                    'has_shortage' => (float) $batches->sum('quantity_available') < $requiredForRemaining,
                ];
            })->values();

            return [
                'type' => 'compound',
                'summary' => 'Racikan kapsul memakai stok bahan per ingredient saat dispense.',
                'has_shortage' => $ingredients->contains(fn (array $ingredient) => $ingredient['has_shortage']),
                'ingredients' => $ingredients->all(),
            ];
        }

        $batches = $this->availableBatchesForMedicine((int) $item->medicine_id, $branchId);
        $branchPrice = $item->medicine?->branchPrices?->firstWhere('branch_id', $branchId);
        $availableQuantity = (float) $batches->sum('quantity_available');

        return [
            'type' => 'in_house',
            'summary' => trim(($item->medicine?->code ?? '-') . ' - ' . ($item->medicine?->name ?? '-')),
            'available_quantity' => $availableQuantity,
            'remaining_quantity' => $remaining,
            'has_shortage' => $availableQuantity < $remaining,
            'nearest_batch' => $batches->first()?->batch_number,
            'nearest_expiry' => $batches->first()?->expired_at?->format('d M Y'),
            'selling_price' => $branchPrice instanceof MedicineBranchPrice ? (float) $branchPrice->selling_price : null,
            'has_price' => $branchPrice instanceof MedicineBranchPrice,
        ];
    }

    private function buildSafetyAlerts(PrescriptionItem $item): array
    {
        $alerts = collect();
        $patient = $item->prescription?->visitRegistration?->patient;
        $relatedMedicines = collect([$item->medicine])
            ->merge($item->compoundIngredients->map(fn ($ingredient) => $ingredient->medicine))
            ->filter();
        $targets = collect([
            $item->display_name,
            ...$relatedMedicines->flatMap(function ($medicine) {
                return [
                    $medicine?->name,
                    $medicine?->generic_name,
                    ...$this->explodeKeywords($medicine?->active_ingredients),
                    ...$this->explodeKeywords($medicine?->allergy_keywords),
                ];
            }),
        ])->filter()->map(fn ($value) => str($value)->lower()->value())->unique()->values();

        if (filled($patient?->allergy_notes)) {
            $keywords = $this->extractPatientAllergyKeywords($patient->allergy_notes);

            foreach ($keywords as $keyword) {
                if ($targets->contains(fn (string $target): bool => str_contains($target, $keyword))) {
                    $alerts->push([
                        'type' => 'safety',
                        'level' => 'danger',
                        'severity' => 'allergy',
                        'blocking' => true,
                        'requires_override' => false,
                        'overridden' => false,
                        'message' => 'Riwayat alergi pasien memuat "' . $keyword . '". Cek ulang sebelum dispense.',
                    ]);
                }
            }
        }

        foreach ($relatedMedicines as $medicine) {
            if (filled($medicine?->contraindication_notes)) {
                $alerts->push([
                    'type' => 'safety',
                    'level' => 'warning',
                    'severity' => 'caution',
                    'blocking' => false,
                    'requires_override' => false,
                    'overridden' => false,
                    'message' => 'Contraindication / caution: ' . trim((string) $medicine->contraindication_notes),
                ]);
            }
        }

        if ($item->medicine_id) {
            $otherItems = $item->prescription?->items
                ?->where('id', '!=', $item->id)
                ->filter(fn ($otherItem) => ! $otherItem->isClosedCancelled());

            $hasDuplicateMedicine = $otherItems?->contains(fn ($otherItem) => $otherItem->medicine_id === $item->medicine_id);

            $genericName = trim(strtolower((string) $item->medicine?->generic_name));
            $hasDuplicateGeneric = $genericName !== ''
                && $otherItems?->contains(fn ($otherItem) => trim(strtolower((string) $otherItem->medicine?->generic_name)) === $genericName);

            $therapeuticClass = trim(strtolower((string) $item->medicine?->therapeutic_class));
            $hasDuplicateClass = $therapeuticClass !== ''
                && $otherItems?->contains(fn ($otherItem) => trim(strtolower((string) $otherItem->medicine?->therapeutic_class)) === $therapeuticClass);

            $activeIngredients = collect($this->explodeKeywords($item->medicine?->active_ingredients))
                ->map(fn ($value) => strtolower((string) $value))
                ->filter()
                ->unique();

            $hasDuplicateIngredient = $activeIngredients->isNotEmpty()
                && $otherItems?->contains(function ($otherItem) use ($activeIngredients): bool {
                    $otherIngredients = collect($this->explodeKeywords($otherItem->medicine?->active_ingredients))
                        ->map(fn ($value) => strtolower((string) $value))
                        ->filter()
                        ->unique();

                    return $otherIngredients->intersect($activeIngredients)->isNotEmpty();
                });

            if ($hasDuplicateMedicine || $hasDuplicateGeneric || $hasDuplicateClass || $hasDuplicateIngredient) {
                $alerts->push([
                    'type' => 'safety',
                    'level' => 'warning',
                    'severity' => 'duplicate_therapy',
                    'blocking' => false,
                    'requires_override' => false,
                    'overridden' => false,
                    'message' => 'Ada item lain dengan medicine, generic, ingredient, atau kelas terapi serupa. Pastikan bukan duplicate therapy.',
                ]);
            }
        }

        $preview = $this->buildDispensingPreview($item);

        if (($preview['has_shortage'] ?? false) === true) {
            $alerts->push([
                'type' => 'safety',
                'level' => 'warning',
                'severity' => 'stock_shortage',
                'blocking' => false,
                'requires_override' => false,
                'overridden' => false,
                'message' => 'Stok aktif tidak cukup untuk memenuhi sisa quantity item ini.',
            ]);
        }

        if (($preview['type'] ?? null) === 'in_house' && ($preview['has_price'] ?? true) === false) {
            $alerts->push([
                'type' => 'safety',
                'level' => 'warning',
                'severity' => 'pricing',
                'blocking' => false,
                'requires_override' => false,
                'overridden' => false,
                'message' => 'Harga jual branch belum diatur. Dispense akan meminta harga manual.',
            ]);
        }

        $alerts = $alerts->merge($this->drugInteractionCheckerService->alertsForItem($item));

        return $alerts
            ->unique(fn (array $alert): string => (string) ($alert['interaction_key'] ?? $alert['message'] ?? spl_object_hash((object) $alert)))
            ->values()
            ->all();
    }

    private function availableBatchesForMedicine(int $medicineId, int $branchId): Collection
    {
        return MedicineBatch::query()
            ->where('medicine_id', $medicineId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('quantity_available', '>', 0)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('expired_at')
                    ->orWhereDate('expired_at', '>=', now()->toDateString());
            })
            ->orderByRaw('CASE WHEN expired_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expired_at')
            ->orderBy('received_at')
            ->get(['id', 'medicine_id', 'batch_number', 'expired_at', 'quantity_available']);
    }

    private function remainingQuantity(PrescriptionItem $item): float
    {
        return max(0, (float) $item->quantity_prescribed - $this->dispensedQuantity($item));
    }

    private function dispensedQuantity(PrescriptionItem $item): float
    {
        return (float) $item->dispenses->sum('quantity_dispensed');
    }

    private function resolveDispensePrice(PrescriptionItem $item, int $branchId, ?float $manualPrice): float
    {
        if ($item->isCompound()) {
            if ($manualPrice === null) {
                throw new PrescriptionManagementException(
                    'Compound capsule memerlukan harga jual manual saat dispensing.',
                    422,
                    'unit_price',
                );
            }

            return $manualPrice;
        }

        if ($manualPrice !== null) {
            return $manualPrice;
        }

        $branchPrice = $item->medicine?->branchPrices
            ?->firstWhere('branch_id', $branchId);

        if (! $branchPrice instanceof MedicineBranchPrice) {
            throw new PrescriptionManagementException(
                'Harga jual medicine untuk branch ini belum diatur di Pharmacy.',
                422,
                'unit_price',
            );
        }

        return (float) $branchPrice->selling_price;
    }

    private function refreshPrescriptionStatus(Prescription $prescription): void
    {
        $items = $prescription->items;

        if ($items->isEmpty()) {
            $prescription->update([
                'status' => 'draft',
            ]);

            return;
        }

        $inHouseItems = $items->filter(fn ($item) => ! $item->isExternal() && ! $item->isClosedCancelled());

        if ($prescription->finalized_at === null) {
            $prescription->update([
                'status' => 'draft',
            ]);

            return;
        }

        if ($inHouseItems->isEmpty()) {
            $prescription->update([
                'status' => 'finalized',
            ]);

            return;
        }

        $openItems = $inHouseItems->filter(fn ($item) => $item->hasOpenFulfillment());
        $anyDispensed = $inHouseItems->contains(function ($item): bool {
            return in_array($item->status, ['partial', 'dispensed', 'partial_external', 'partial_cancelled'], true)
                || $item->dispenses->isNotEmpty();
        });

        $prescription->update([
            'status' => $openItems->isEmpty()
                ? ($anyDispensed ? 'dispensed' : 'finalized')
                : ($anyDispensed ? 'partial_dispensed' : 'finalized'),
        ]);
    }

    private function explodeKeywords(?string $value): array
    {
        if (! filled($value)) {
            return [];
        }

        return collect(preg_split('/[\n,;\/]+/', (string) $value))
            ->map(fn ($entry) => trim((string) $entry))
            ->filter(fn ($entry) => $entry !== '')
            ->values()
            ->all();
    }

    private function extractPatientAllergyKeywords(?string $value): Collection
    {
        if (! filled($value)) {
            return collect();
        }

        return collect(preg_split('/[\n,;\/]+/', strtolower((string) $value)))
            ->flatMap(function ($entry): array {
                $normalized = preg_replace('/\b(alergi|allergy|riwayat|pasien|terhadap|dengan|pada)\b/u', ' ', (string) $entry);
                $normalized = preg_replace('/\s+/', ' ', trim((string) $normalized));

                if ($normalized === '') {
                    return [];
                }

                $tokens = preg_split('/[\s\-]+/', $normalized) ?: [];

                return collect([$normalized, ...$tokens])
                    ->map(fn ($keyword) => trim((string) $keyword))
                    ->filter(fn ($keyword) => $keyword !== '' && strlen($keyword) >= 3)
                    ->unique()
                    ->values()
                    ->all();
            })
            ->unique()
            ->values();
    }

    private function syncVisitAfterMutation(VisitRegistration $visit): void
    {
        $this->clinicalBillingService->syncVisit($visit->fresh([
            'medicalRecord.doctor',
            'prescription.items.dispenses',
            'visitMedicalServices.medicalService',
            'visitProcedures.procedureMaster',
            'laboratoryOrders.laboratoryTest',
            'invoice.items',
        ]));
        $this->clinicalWorkflowService->refreshVisit($visit->fresh([
            'medicalRecord',
            'prescription.items.dispenses',
            'visitMedicalServices',
            'visitProcedures',
            'laboratoryOrders',
            'invoice',
        ]));
    }

    private function abilities(): array
    {
        $user = auth()->user();

        return [
            'create' => $user?->hasRole('super-admin') || ($user?->can('create prescription management') ?? false),
            'edit' => $user?->hasRole('super-admin') || ($user?->can('edit prescription management') ?? false),
            'delete' => $user?->hasRole('super-admin') || ($user?->can('delete prescription management') ?? false),
            'dispense' => $user?->hasRole('super-admin') || ($user?->can('edit prescription management') ?? false),
            'finalize' => $user?->hasRole('super-admin') || ($user?->can('edit prescription management') ?? false),
            'override' => $user?->hasRole('super-admin') || ($user?->can('edit prescription management') ?? false),
        ];
    }

    private function refreshReorderForItem(PrescriptionItem $item): void
    {
        $branchId = (int) ($item->prescription?->visitRegistration?->branch_id ?? 0);

        if (! $branchId) {
            return;
        }

        collect([$item->medicine])
            ->merge($item->compoundIngredients->map(fn ($ingredient) => $ingredient->medicine))
            ->filter()
            ->unique(fn (Medicine $medicine): int => $medicine->id)
            ->each(fn (Medicine $medicine) => $this->reorderPointService->refreshForMedicineBranch($medicine->id, $branchId));
    }

    private function prescriptionAuditSnapshot(Prescription $prescription): array
    {
        $prescription->loadMissing($this->prescriptionRelations());

        return [
            'id' => $prescription->getKey(),
            'visit_registration_id' => $prescription->visit_registration_id,
            'patient_id' => $prescription->patient_id,
            'patient_name' => $prescription->patient?->full_name,
            'branch_id' => $prescription->branch_id,
            'branch_code' => $prescription->branch?->code,
            'section_id' => $prescription->section_id,
            'section_name' => $prescription->section?->name,
            'doctor_id' => $prescription->doctor_id,
            'doctor_name' => $prescription->doctor?->displayName(),
            'status' => $prescription->status,
            'notes' => $prescription->notes,
            'finalized_at' => $prescription->finalized_at?->toIso8601String(),
            'items_count' => $prescription->items->count(),
        ];
    }

    private function prescriptionItemAuditSnapshot(PrescriptionItem $item): array
    {
        $item->loadMissing($this->prescriptionItemRelations());

        return [
            'id' => $item->getKey(),
            'prescription_id' => $item->prescription_id,
            'prescription_status' => $item->prescription?->status,
            'item_type' => $item->item_type,
            'medicine_id' => $item->medicine_id,
            'medicine_name' => $item->medicine?->name,
            'display_name' => $item->display_name,
            'route' => $item->route,
            'dose_amount' => $item->dose_amount !== null ? (float) $item->dose_amount : null,
            'dose_unit' => $item->dose_unit,
            'frequency' => $item->frequency,
            'duration_days' => $item->duration_days,
            'instruction' => $item->instruction,
            'quantity_prescribed' => (float) $item->quantity_prescribed,
            'dispense_unit' => $item->dispense_unit,
            'weight_snapshot_kg' => $item->weight_snapshot_kg !== null ? (float) $item->weight_snapshot_kg : null,
            'status' => $item->status,
            'notes' => $item->notes,
            'closed_remaining_status' => $item->closed_remaining_status,
            'closed_remaining_reason' => $item->closed_remaining_reason,
            'compound_ingredients' => $item->compoundIngredients->map(fn ($ingredient): array => [
                'medicine_id' => $ingredient->medicine_id,
                'quantity_required' => (float) $ingredient->quantity_required,
                'unit' => $ingredient->unit,
            ])->values()->all(),
            'dispensed_quantity' => $this->dispensedQuantity($item),
        ];
    }

    private function dispenseAuditSnapshot(PrescriptionDispense $dispense): array
    {
        $dispense->loadMissing($this->dispenseRelations());

        return [
            'id' => $dispense->getKey(),
            'prescription_item_id' => $dispense->prescription_item_id,
            'visit_registration_id' => $dispense->visit_registration_id,
            'branch_id' => $dispense->branch_id,
            'dispensed_by_user_id' => $dispense->dispensed_by_user_id,
            'quantity_dispensed' => (float) $dispense->quantity_dispensed,
            'unit_price' => (float) $dispense->unit_price,
            'subtotal' => (float) $dispense->subtotal,
            'dispensed_at' => $dispense->dispensed_at?->toIso8601String(),
            'notes' => $dispense->notes,
            'batch_usages' => $dispense->batchUsages->map(fn ($usage): array => [
                'medicine_batch_id' => $usage->medicine_batch_id,
                'batch_number' => $usage->medicineBatch?->batch_number,
                'quantity_used' => (float) $usage->quantity_used,
            ])->values()->all(),
        ];
    }

    private function visitRelations(): array
    {
        return [
            'patient:id,full_name,phone',
            'patientBranchRecord:id,medical_record_no',
            'branch:id,name,code',
            'section:id,name,code,type',
            'medicalRecord:id,visit_registration_id,status,doctor_id',
            'medicalRecord.doctor:id,full_name,title_prefix,title_suffix,consultation_fee',
            'doctor:id,full_name,title_prefix,title_suffix',
            'prescription' => fn ($query) => $query->with([
                'items.dispenses',
            ]),
        ];
    }

    private function prescriptionRelations(): array
    {
        return [
            'patient:id,full_name,phone',
            'branch:id,name,code',
            'section:id,name,code,type',
            'doctor:id,full_name,title_prefix,title_suffix',
            'visitRegistration:id,patient_id,patient_branch_record_id,branch_id,section_id,doctor_id,visit_date,care_stage',
            'visitRegistration.patientBranchRecord:id,medical_record_no',
            'items.medicine:id,code,name,generic_name,strength,base_unit,active_ingredients,allergy_keywords,therapeutic_class,contraindication_notes',
            'items.compoundIngredients.medicine:id,code,name,generic_name,base_unit,active_ingredients,allergy_keywords,therapeutic_class,contraindication_notes',
            'items.dispenses',
            'interactionOverrides:id,prescription_id,prescription_item_id,interaction_key,interaction_title,severity,reason,overridden_by_user_id,overridden_at',
        ];
    }

    private function prescriptionItemRelations(): array
    {
        return [
            'medicine:id,code,name,generic_name,strength,base_unit,active_ingredients,allergy_keywords,therapeutic_class,contraindication_notes',
            'medicine.branchPrices:id,branch_id,medicine_id,selling_price,is_active',
            'compoundIngredients.medicine:id,code,name,generic_name,base_unit,active_ingredients,allergy_keywords,therapeutic_class,contraindication_notes',
            'dispenses' => fn ($builder) => $builder
                ->with('batchUsages.medicineBatch:id,batch_number,expired_at')
                ->orderByDesc('dispensed_at'),
            'interactionOverrides:id,prescription_item_id,interaction_key,reason,overridden_by_user_id,overridden_at',
            'prescription:id,visit_registration_id,patient_id,branch_id,section_id,doctor_id,status,notes,finalized_at',
            'prescription.items:id,prescription_id,medicine_id,status,display_name',
            'prescription.items.medicine:id,generic_name,active_ingredients,therapeutic_class',
            'prescription.interactionOverrides:id,prescription_id,prescription_item_id,interaction_key,reason',
            'prescription.visitRegistration:id,patient_id,patient_branch_record_id,branch_id,section_id,doctor_id,doctor_schedule_id,visit_date',
            'prescription.visitRegistration.patient:id,full_name,phone,allergy_notes',
            'prescription.visitRegistration.patientBranchRecord:id,medical_record_no',
            'prescription.visitRegistration.branch:id,name,code',
            'prescription.visitRegistration.section:id,name,code,type',
            'prescription.visitRegistration.doctor:id,full_name,title_prefix,title_suffix',
            'prescription.visitRegistration.doctorSchedule:id,room_label',
        ];
    }

    private function dispenseRelations(): array
    {
        return [
            'dispensedBy:id,name',
            'batchUsages.medicineBatch:id,batch_number,expired_at',
            'prescriptionItem.medicine:id,code,name,generic_name,strength,base_unit',
            'prescriptionItem.compoundIngredients.medicine:id,code,name,generic_name,base_unit',
            'prescriptionItem.prescription.visitRegistration.patient:id,full_name,phone',
            'prescriptionItem.prescription.visitRegistration.patientBranchRecord:id,medical_record_no',
            'prescriptionItem.prescription.visitRegistration.branch:id,name,code',
            'prescriptionItem.prescription.visitRegistration.medicalRecord.doctor:id,full_name,title_prefix,title_suffix',
        ];
    }
}
