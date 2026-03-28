<?php

namespace App\Modules\MedicalServices\Services;

use App\Models\Branch;
use App\Models\MedicalService;
use App\Models\User;
use App\Models\VisitMedicalService;
use App\Models\VisitRegistration;
use App\Modules\MedicalServices\Exceptions\MedicalServiceManagementException;
use App\Services\AuditLogService;
use App\Services\ClinicalBillingService;
use App\Services\ClinicalWorkflowService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MedicalServiceModuleService
{
    private const MASTER_ALLOWED_SORTS = [
        'code',
        'name',
        'created_at',
    ];

    private const ORDER_ALLOWED_SORTS = [
        'ordered_at',
        'created_at',
        'status',
        'subtotal',
    ];

    public function __construct(
        private readonly ClinicalBillingService $clinicalBillingService,
        private readonly ClinicalWorkflowService $clinicalWorkflowService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);

        return [
            'filters' => $filters,
            'masters' => $this->masterTable($filters),
            'orders' => $this->orderTable($filters),
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'name', 'code']),
            'visitOptions' => $this->visitOptions(),
            'masterOptions' => MedicalService::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'service_type', 'default_fee']),
            'abilities' => $this->abilities(),
            'masterSortOptions' => [
                'name' => 'Nama service',
                'code' => 'Kode service',
                'created_at' => 'Waktu dibuat',
            ],
            'orderSortOptions' => [
                'ordered_at' => 'Waktu order',
                'created_at' => 'Waktu dibuat',
                'status' => 'Status',
                'subtotal' => 'Subtotal',
            ],
            'perPageOptions' => [10, 25, 50, 100],
        ];
    }

    public function createMaster(array $payload, User $actor): array
    {
        return $this->transactional(function () use ($payload, $actor): array {
            $normalizedCode = strtoupper(trim($payload['code']));
            $existing = MedicalService::query()
                ->with('branchPrices')
                ->where('code', $normalizedCode)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($this->masterMatchesDesiredState($existing, $payload)) {
                    return ['medicalService' => $existing, 'changed' => false];
                }

                throw new MedicalServiceManagementException(
                    'Kode medical service sudah dipakai oleh data lain.',
                    409,
                    'code',
                );
            }

            $medicalService = MedicalService::query()->create($this->masterAttributes($payload));
            $this->syncBranchPrices($medicalService, $payload['branch_prices'] ?? []);
            $medicalService = $medicalService->fresh($this->masterRelations());

            $this->auditLogService->log(
                module: 'medical_service_management',
                action: 'create_master',
                auditable: $medicalService,
                description: sprintf('Medical service %s dibuat oleh %s.', $medicalService->code, $actor->email),
                after: $this->masterAuditSnapshot($medicalService),
                meta: ['actor_user_id' => $actor->getKey()],
            );

            return ['medicalService' => $medicalService, 'changed' => true];
        });
    }

    public function updateMaster(MedicalService $medicalService, array $payload, User $actor): array
    {
        return $this->transactional(function () use ($medicalService, $payload, $actor): array {
            $lockedMedicalService = $this->lockMaster($medicalService->getKey());
            $normalizedCode = strtoupper(trim($payload['code']));

            if ($normalizedCode !== $lockedMedicalService->code) {
                $duplicate = MedicalService::query()
                    ->where('code', $normalizedCode)
                    ->whereKeyNot($lockedMedicalService->getKey())
                    ->lockForUpdate()
                    ->exists();

                if ($duplicate) {
                    throw new MedicalServiceManagementException(
                        'Kode medical service sudah dipakai oleh data lain.',
                        409,
                        'code',
                    );
                }
            }

            if ($this->masterMatchesDesiredState($lockedMedicalService, $payload)) {
                return ['medicalService' => $lockedMedicalService, 'changed' => false];
            }

            $before = $this->masterAuditSnapshot($lockedMedicalService);

            $lockedMedicalService->fill($this->masterAttributes($payload));
            $lockedMedicalService->save();
            $this->syncBranchPrices($lockedMedicalService, $payload['branch_prices'] ?? []);
            $lockedMedicalService = $lockedMedicalService->fresh($this->masterRelations());

            $this->auditLogService->log(
                module: 'medical_service_management',
                action: 'update_master',
                auditable: $lockedMedicalService,
                description: sprintf('Medical service %s diperbarui oleh %s.', $lockedMedicalService->code, $actor->email),
                before: $before,
                after: $this->masterAuditSnapshot($lockedMedicalService),
                meta: ['actor_user_id' => $actor->getKey()],
            );

            return ['medicalService' => $lockedMedicalService, 'changed' => true];
        });
    }

    public function archiveMaster(MedicalService $medicalService, User $actor): array
    {
        return $this->transactional(function () use ($medicalService, $actor): array {
            $lockedMedicalService = $this->lockMaster($medicalService->getKey());

            if (! $lockedMedicalService->is_active) {
                return ['medicalService' => $lockedMedicalService, 'changed' => false];
            }

            $before = $this->masterAuditSnapshot($lockedMedicalService);

            $lockedMedicalService->update(['is_active' => false]);
            $lockedMedicalService = $lockedMedicalService->fresh($this->masterRelations());

            $this->auditLogService->log(
                module: 'medical_service_management',
                action: 'archive_master',
                auditable: $lockedMedicalService,
                description: sprintf('Medical service %s diarsipkan oleh %s.', $lockedMedicalService->code, $actor->email),
                before: $before,
                after: $this->masterAuditSnapshot($lockedMedicalService),
                meta: ['actor_user_id' => $actor->getKey()],
            );

            return ['medicalService' => $lockedMedicalService, 'changed' => true];
        });
    }

    public function createOrder(array $payload, User $actor): array
    {
        return $this->transactional(function () use ($payload, $actor): array {
            $visit = $this->resolveManagedVisit((int) $payload['visit_registration_id'], true);
            $medicalService = $this->resolveManagedService((int) $payload['medical_service_id']);
            $order = VisitMedicalService::query()->create(
                $this->orderAttributes($visit, $medicalService, $payload, $actor)
            );

            $this->syncVisit($visit);
            $order = $order->fresh($this->orderRelations());

            $this->auditLogService->log(
                module: 'medical_service_management',
                action: 'create_order',
                auditable: $order,
                description: sprintf('Medical service order %s dibuat oleh %s.', $order->getKey(), $actor->email),
                after: $this->orderAuditSnapshot($order),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $order->visit_registration_id,
                    'branch_id' => $order->branch_id,
                ],
            );

            return ['order' => $order, 'changed' => true];
        });
    }

    public function updateOrder(VisitMedicalService $order, array $payload, User $actor): array
    {
        return $this->transactional(function () use ($order, $payload, $actor): array {
            $lockedOrder = $this->lockOrder($order->getKey());

            if ((int) $payload['visit_registration_id'] !== (int) $lockedOrder->visit_registration_id) {
                throw new MedicalServiceManagementException(
                    'Service order yang sudah tercatat tidak boleh dipindahkan ke visit lain.',
                    409,
                    'visit_registration_id',
                );
            }

            $visit = $this->resolveManagedVisit((int) $lockedOrder->visit_registration_id, true);
            $allowInactiveService = (int) $lockedOrder->medical_service_id === (int) $payload['medical_service_id'];
            $medicalService = $this->resolveManagedService((int) $payload['medical_service_id'], $allowInactiveService);

            if ($lockedOrder->isCancelled()) {
                if ($this->orderMatchesDesiredState($lockedOrder, $visit, $medicalService, $payload, $actor)) {
                    return ['order' => $lockedOrder, 'changed' => false];
                }

                throw new MedicalServiceManagementException(
                    'Service order yang sudah cancelled tidak bisa diubah lagi.',
                    409,
                    'order',
                );
            }

            if ($lockedOrder->isCompleted()) {
                if ($this->orderMatchesDesiredState($lockedOrder, $visit, $medicalService, $payload, $actor)) {
                    return ['order' => $lockedOrder, 'changed' => false];
                }

                throw new MedicalServiceManagementException(
                    'Service order yang sudah completed tidak bisa diubah lagi.',
                    409,
                    'order',
                );
            }

            if ($this->orderMatchesDesiredState($lockedOrder, $visit, $medicalService, $payload, $actor)) {
                return ['order' => $lockedOrder, 'changed' => false];
            }

            $before = $this->orderAuditSnapshot($lockedOrder);

            $lockedOrder->fill($this->orderAttributes($visit, $medicalService, $payload, $actor, $lockedOrder));
            $lockedOrder->save();
            $this->syncVisit($visit);
            $lockedOrder = $lockedOrder->fresh($this->orderRelations());

            $this->auditLogService->log(
                module: 'medical_service_management',
                action: 'update_order',
                auditable: $lockedOrder,
                description: sprintf('Medical service order %s diperbarui oleh %s.', $lockedOrder->getKey(), $actor->email),
                before: $before,
                after: $this->orderAuditSnapshot($lockedOrder),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $lockedOrder->visit_registration_id,
                    'branch_id' => $lockedOrder->branch_id,
                ],
            );

            return ['order' => $lockedOrder, 'changed' => true];
        });
    }

    public function cancelOrder(VisitMedicalService $order, User $actor): array
    {
        return $this->transactional(function () use ($order, $actor): array {
            $lockedOrder = $this->lockOrder($order->getKey());

            if ($lockedOrder->isCancelled()) {
                return ['order' => $lockedOrder, 'changed' => false];
            }

            if ($lockedOrder->isCompleted()) {
                throw new MedicalServiceManagementException(
                    'Service order yang sudah completed tidak bisa dibatalkan. Gunakan proses koreksi yang terkontrol.',
                    409,
                    'service_order',
                );
            }

            $visit = $this->resolveManagedVisit((int) $lockedOrder->visit_registration_id, true);
            $before = $this->orderAuditSnapshot($lockedOrder);

            $lockedOrder->update([
                'status' => 'cancelled',
                'completed_at' => null,
                'cancelled_at' => $lockedOrder->cancelled_at ?? now(),
                'performed_by_user_id' => null,
            ]);

            $this->syncVisit($visit);
            $lockedOrder = $lockedOrder->fresh($this->orderRelations());

            $this->auditLogService->log(
                module: 'medical_service_management',
                action: 'cancel_order',
                auditable: $lockedOrder,
                description: sprintf('Medical service order %s dibatalkan oleh %s.', $lockedOrder->getKey(), $actor->email),
                before: $before,
                after: $this->orderAuditSnapshot($lockedOrder),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $lockedOrder->visit_registration_id,
                    'branch_id' => $lockedOrder->branch_id,
                ],
            );

            return ['order' => $lockedOrder, 'changed' => true];
        });
    }

    public function masterPayload(MedicalService $medicalService): array
    {
        $medicalService->loadMissing($this->masterRelations());

        return [
            'id' => $medicalService->getKey(),
            'code' => $medicalService->code,
            'name' => $medicalService->name,
            'service_type' => $medicalService->service_type,
            'description' => $medicalService->description,
            'default_fee' => (float) $medicalService->default_fee,
            'is_active' => $medicalService->is_active,
            'branch_prices' => $medicalService->branchPrices->map(fn ($price): array => [
                'branch_id' => $price->branch_id,
                'branch_code' => $price->branch?->code,
                'price' => (float) $price->price,
                'is_active' => (bool) $price->is_active,
            ])->values()->all(),
        ];
    }

    public function orderPayload(VisitMedicalService $order): array
    {
        $order->loadMissing($this->orderRelations());

        return [
            'id' => $order->getKey(),
            'visit_registration_id' => $order->visit_registration_id,
            'branch_id' => $order->branch_id,
            'branch_code' => $order->branch?->code,
            'section_id' => $order->section_id,
            'section_name' => $order->section?->name,
            'medical_service_id' => $order->medical_service_id,
            'medical_service_name' => $order->medicalService?->name,
            'patient_name' => $order->visitRegistration?->patient?->full_name,
            'medical_record_no' => $order->visitRegistration?->patientBranchRecord?->medical_record_no,
            'ordered_by_user' => $order->orderedByUser?->name,
            'performed_by_user' => $order->performedByUser?->name,
            'quantity' => (float) $order->quantity,
            'unit_price' => (float) $order->unit_price,
            'subtotal' => (float) $order->subtotal,
            'status' => $order->status,
            'ordered_at' => $order->ordered_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'notes' => $order->notes,
        ];
    }

    private function transactional(callable $callback): mixed
    {
        try {
            return DB::transaction($callback);
        } catch (MedicalServiceManagementException $exception) {
            throw $exception;
        } catch (ValidationException $exception) {
            throw $this->mapValidationException($exception);
        }
    }

    private function mapValidationException(ValidationException $exception): MedicalServiceManagementException
    {
        $errors = $exception->errors();
        $key = (string) (array_key_first($errors) ?? 'medical_service');
        $message = (string) ($errors[$key][0] ?? $exception->getMessage());

        return new MedicalServiceManagementException($message, 422, $key);
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'search' => trim((string) ($filters['search'] ?? '')),
            'branch' => (string) ($filters['branch'] ?? ''),
            'status' => (string) ($filters['status'] ?? ''),
            'date' => (string) ($filters['date'] ?? now()->toDateString()),
            'master_status' => (string) ($filters['master_status'] ?? ''),
            'master_sort_by' => (string) ($filters['master_sort_by'] ?? 'name'),
            'master_sort_direction' => (string) ($filters['master_sort_direction'] ?? 'asc'),
            'master_per_page' => (int) ($filters['master_per_page'] ?? 10),
            'order_sort_by' => (string) ($filters['order_sort_by'] ?? 'ordered_at'),
            'order_sort_direction' => (string) ($filters['order_sort_direction'] ?? 'desc'),
            'order_per_page' => (int) ($filters['order_per_page'] ?? 10),
        ];
    }

    private function masterTable(array $filters): LengthAwarePaginator
    {
        return MedicalService::query()
            ->with($this->masterRelations())
            ->searchForManagement($filters['search'])
            ->filterActiveState($filters['master_status'])
            ->orderByAllowed($filters['master_sort_by'], $filters['master_sort_direction'], self::MASTER_ALLOWED_SORTS)
            ->paginate($filters['master_per_page'], ['*'], 'master_page')
            ->withQueryString();
    }

    private function orderTable(array $filters): LengthAwarePaginator
    {
        return VisitMedicalService::query()
            ->with($this->orderRelations())
            ->searchForManagement($filters['search'])
            ->filterBranch($filters['branch'])
            ->filterStatus($filters['status'])
            ->filterDate($filters['date'])
            ->orderByAllowed($filters['order_sort_by'], $filters['order_sort_direction'], self::ORDER_ALLOWED_SORTS)
            ->paginate($filters['order_per_page'], ['*'], 'order_page')
            ->withQueryString();
    }

    private function visitOptions(): Collection
    {
        return VisitRegistration::query()
            ->with([
                'patient:id,full_name,phone',
                'patientBranchRecord:id,medical_record_no',
                'branch:id,name,code',
                'section:id,name,code',
                'medicalRecord:id,visit_registration_id,status',
            ])
            ->whereHas('medicalRecord')
            ->whereNotIn('care_stage', ['cancelled', 'completed'])
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->limit(150)
            ->get(['id', 'patient_id', 'patient_branch_record_id', 'branch_id', 'section_id', 'visit_date', 'care_stage']);
    }

    private function resolveManagedVisit(int $visitRegistrationId, bool $lock = false): VisitRegistration
    {
        $query = VisitRegistration::query()->with('medicalRecord');

        if ($lock) {
            $query->lockForUpdate();
        }

        $visit = $query->find($visitRegistrationId);

        if ($visit === null) {
            throw new MedicalServiceManagementException('Visit registration tidak ditemukan.', 404, 'visit_registration_id');
        }

        if (! $visit->medicalRecord) {
            throw new MedicalServiceManagementException(
                'Service order hanya bisa dibuat untuk visit yang sudah punya medical record.',
                422,
                'visit_registration_id',
            );
        }

        if (in_array($visit->care_stage, ['cancelled', 'completed'], true)) {
            throw new MedicalServiceManagementException(
                'Visit ini sudah selesai atau dibatalkan.',
                409,
                'visit_registration_id',
            );
        }

        return $visit;
    }

    private function resolveManagedService(int $serviceId, bool $allowInactive = false): MedicalService
    {
        $medicalService = MedicalService::query()->with('branchPrices')->find($serviceId);

        if ($medicalService === null) {
            throw new MedicalServiceManagementException('Medical service tidak ditemukan.', 404, 'medical_service_id');
        }

        if (! $allowInactive && ! $medicalService->is_active) {
            throw new MedicalServiceManagementException(
                'Medical service yang tidak aktif tidak bisa dipakai untuk order baru.',
                409,
                'medical_service_id',
            );
        }

        return $medicalService;
    }

    private function lockMaster(int $serviceId): MedicalService
    {
        $medicalService = MedicalService::query()
            ->with($this->masterRelations())
            ->lockForUpdate()
            ->find($serviceId);

        if ($medicalService === null) {
            throw new MedicalServiceManagementException('Medical service tidak ditemukan.', 404, 'medicalService');
        }

        return $medicalService;
    }

    private function lockOrder(int $orderId): VisitMedicalService
    {
        $order = VisitMedicalService::query()
            ->with($this->orderRelations())
            ->lockForUpdate()
            ->find($orderId);

        if ($order === null) {
            throw new MedicalServiceManagementException('Service order tidak ditemukan.', 404, 'order');
        }

        return $order;
    }

    private function masterAttributes(array $payload): array
    {
        return [
            'code' => strtoupper(trim($payload['code'])),
            'name' => trim($payload['name']),
            'service_type' => $payload['service_type'],
            'description' => $payload['description'] ?? null,
            'default_fee' => (float) $payload['default_fee'],
            'is_active' => (bool) $payload['is_active'],
        ];
    }

    private function normalizedBranchPrices(array $branchPrices): array
    {
        return collect($branchPrices)
            ->mapWithKeys(fn ($price, $branchId): array => [
                (string) $branchId => $price === null || $price === '' ? null : (float) $price,
            ])
            ->all();
    }

    private function masterMatchesDesiredState(MedicalService $medicalService, array $payload): bool
    {
        $attributes = $this->masterAttributes($payload);
        $currentPrices = $medicalService->branchPrices
            ->mapWithKeys(fn ($price): array => [(string) $price->branch_id => (float) $price->price])
            ->all();

        return $medicalService->code === $attributes['code']
            && $medicalService->name === $attributes['name']
            && $medicalService->service_type === $attributes['service_type']
            && $medicalService->description === $attributes['description']
            && (float) $medicalService->default_fee === (float) $attributes['default_fee']
            && (bool) $medicalService->is_active === (bool) $attributes['is_active']
            && $currentPrices === array_filter(
                $this->normalizedBranchPrices($payload['branch_prices'] ?? []),
                fn ($price) => $price !== null
            );
    }

    private function syncBranchPrices(MedicalService $service, array $branchPrices): void
    {
        $normalized = $this->normalizedBranchPrices($branchPrices);

        foreach (Branch::query()->pluck('id') as $branchId) {
            $price = $normalized[(string) $branchId] ?? null;

            if ($price === null) {
                $service->branchPrices()->where('branch_id', $branchId)->delete();
                continue;
            }

            $service->branchPrices()->updateOrCreate(
                ['branch_id' => $branchId],
                ['price' => $price, 'is_active' => true],
            );
        }
    }

    private function orderAttributes(
        VisitRegistration $visit,
        MedicalService $service,
        array $payload,
        User $actor,
        ?VisitMedicalService $existing = null,
    ): array {
        $this->assertOrderStatusAllowed($existing?->status, $payload['status']);

        $quantity = (float) $payload['quantity'];
        $unitPrice = (float) ($service->branchPrices->where('is_active', true)->firstWhere('branch_id', $visit->branch_id)?->price ?? $service->default_fee);

        return [
            'visit_registration_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'medical_service_id' => $service->id,
            'ordered_by_user_id' => $existing?->ordered_by_user_id ?? $actor->getKey(),
            'performed_by_user_id' => $payload['status'] === 'completed' ? $actor->getKey() : null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => round($quantity * $unitPrice, 2),
            'status' => $payload['status'],
            'ordered_at' => $existing?->ordered_at ?? now(),
            'completed_at' => $payload['status'] === 'completed' ? ($existing?->completed_at ?? now()) : null,
            'cancelled_at' => $payload['status'] === 'cancelled' ? ($existing?->cancelled_at ?? now()) : null,
            'notes' => $payload['notes'] ?? null,
        ];
    }

    private function orderMatchesDesiredState(
        VisitMedicalService $order,
        VisitRegistration $visit,
        MedicalService $service,
        array $payload,
        User $actor,
    ): bool {
        $attributes = $this->orderAttributes($visit, $service, $payload, $actor, $order);

        return (int) $order->visit_registration_id === (int) $attributes['visit_registration_id']
            && (int) $order->branch_id === (int) $attributes['branch_id']
            && (int) ($order->section_id ?? 0) === (int) ($attributes['section_id'] ?? 0)
            && (int) $order->medical_service_id === (int) $attributes['medical_service_id']
            && (int) ($order->ordered_by_user_id ?? 0) === (int) ($attributes['ordered_by_user_id'] ?? 0)
            && (int) ($order->performed_by_user_id ?? 0) === (int) ($attributes['performed_by_user_id'] ?? 0)
            && (float) $order->quantity === (float) $attributes['quantity']
            && (float) $order->unit_price === (float) $attributes['unit_price']
            && (float) $order->subtotal === (float) $attributes['subtotal']
            && $order->status === $attributes['status']
            && $order->notes === $attributes['notes']
            && $order->completed_at?->equalTo($attributes['completed_at'])
            && $order->cancelled_at?->equalTo($attributes['cancelled_at']);
    }

    private function assertOrderStatusAllowed(?string $currentStatus, string $targetStatus): void
    {
        if ($currentStatus === null) {
            return;
        }

        $allowedTransitions = [
            'ordered' => ['ordered', 'completed', 'cancelled'],
            'completed' => ['completed'],
            'cancelled' => ['cancelled'],
        ];

        if (! in_array($targetStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            throw new MedicalServiceManagementException(
                'Perubahan status service order tidak valid untuk kondisi saat ini.',
                409,
                'status',
            );
        }
    }

    private function syncVisit(VisitRegistration $visit): void
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
            'create' => $user?->hasRole('super-admin') || ($user?->can('create medical service management') ?? false),
            'edit' => $user?->hasRole('super-admin') || ($user?->can('edit medical service management') ?? false),
            'delete' => $user?->hasRole('super-admin') || ($user?->can('delete medical service management') ?? false),
        ];
    }

    private function masterAuditSnapshot(MedicalService $medicalService): array
    {
        $medicalService->loadMissing($this->masterRelations());

        return [
            'id' => $medicalService->getKey(),
            'code' => $medicalService->code,
            'name' => $medicalService->name,
            'service_type' => $medicalService->service_type,
            'description' => $medicalService->description,
            'default_fee' => (float) $medicalService->default_fee,
            'is_active' => $medicalService->is_active,
            'branch_prices' => $medicalService->branchPrices->map(fn ($price): array => [
                'branch_id' => $price->branch_id,
                'price' => (float) $price->price,
                'is_active' => (bool) $price->is_active,
            ])->values()->all(),
        ];
    }

    private function orderAuditSnapshot(VisitMedicalService $order): array
    {
        $order->loadMissing($this->orderRelations());

        return [
            'id' => $order->getKey(),
            'visit_registration_id' => $order->visit_registration_id,
            'branch_id' => $order->branch_id,
            'section_id' => $order->section_id,
            'medical_service_id' => $order->medical_service_id,
            'medical_service_name' => $order->medicalService?->name,
            'ordered_by_user_id' => $order->ordered_by_user_id,
            'performed_by_user_id' => $order->performed_by_user_id,
            'quantity' => (float) $order->quantity,
            'unit_price' => (float) $order->unit_price,
            'subtotal' => (float) $order->subtotal,
            'status' => $order->status,
            'ordered_at' => $order->ordered_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'notes' => $order->notes,
        ];
    }

    private function masterRelations(): array
    {
        return ['branchPrices.branch:id,name,code'];
    }

    private function orderRelations(): array
    {
        return [
            'visitRegistration.patient:id,full_name,phone',
            'visitRegistration.patientBranchRecord:id,medical_record_no',
            'branch:id,name,code',
            'section:id,name,code',
            'medicalService:id,code,name,service_type',
            'orderedByUser:id,name',
            'performedByUser:id,name',
        ];
    }
}
