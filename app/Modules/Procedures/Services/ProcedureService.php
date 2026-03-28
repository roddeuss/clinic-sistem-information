<?php

namespace App\Modules\Procedures\Services;

use App\Models\Branch;
use App\Models\ProcedureMaster;
use App\Models\User;
use App\Models\VisitProcedure;
use App\Models\VisitRegistration;
use App\Modules\Procedures\Exceptions\ProcedureManagementException;
use App\Services\AuditLogService;
use App\Services\ClinicalBillingService;
use App\Services\ClinicalWorkflowService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcedureService
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
            'masterOptions' => ProcedureMaster::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'performer_scope', 'requires_doctor_order', 'default_fee']),
            'abilities' => $this->abilities(),
            'masterSortOptions' => [
                'name' => 'Nama master',
                'code' => 'Kode master',
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
            $existing = ProcedureMaster::query()
                ->with('branchPrices')
                ->where('code', $normalizedCode)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($this->masterMatchesDesiredState($existing, $payload)) {
                    return ['master' => $existing, 'changed' => false];
                }

                throw new ProcedureManagementException(
                    'Kode procedure master sudah dipakai oleh data lain.',
                    409,
                    'code',
                );
            }

            $master = ProcedureMaster::query()->create($this->masterAttributes($payload));
            $this->syncBranchPrices($master, $payload['branch_prices'] ?? []);
            $master = $master->fresh($this->masterRelations());

            $this->auditLogService->log(
                module: 'procedure_management',
                action: 'create_master',
                auditable: $master,
                description: sprintf('Procedure master %s dibuat oleh %s.', $master->code, $actor->email),
                after: $this->masterAuditSnapshot($master),
                meta: ['actor_user_id' => $actor->getKey()],
            );

            return ['master' => $master, 'changed' => true];
        });
    }

    public function updateMaster(ProcedureMaster $master, array $payload, User $actor): array
    {
        return $this->transactional(function () use ($master, $payload, $actor): array {
            $lockedMaster = $this->lockMaster($master->getKey());
            $normalizedCode = strtoupper(trim($payload['code']));

            if ($normalizedCode !== $lockedMaster->code) {
                $duplicate = ProcedureMaster::query()
                    ->where('code', $normalizedCode)
                    ->whereKeyNot($lockedMaster->getKey())
                    ->lockForUpdate()
                    ->exists();

                if ($duplicate) {
                    throw new ProcedureManagementException(
                        'Kode procedure master sudah dipakai oleh data lain.',
                        409,
                        'code',
                    );
                }
            }

            if ($this->masterMatchesDesiredState($lockedMaster, $payload)) {
                return ['master' => $lockedMaster, 'changed' => false];
            }

            $before = $this->masterAuditSnapshot($lockedMaster);

            $lockedMaster->fill($this->masterAttributes($payload));
            $lockedMaster->save();
            $this->syncBranchPrices($lockedMaster, $payload['branch_prices'] ?? []);
            $lockedMaster = $lockedMaster->fresh($this->masterRelations());

            $this->auditLogService->log(
                module: 'procedure_management',
                action: 'update_master',
                auditable: $lockedMaster,
                description: sprintf('Procedure master %s diperbarui oleh %s.', $lockedMaster->code, $actor->email),
                before: $before,
                after: $this->masterAuditSnapshot($lockedMaster),
                meta: ['actor_user_id' => $actor->getKey()],
            );

            return ['master' => $lockedMaster, 'changed' => true];
        });
    }

    public function archiveMaster(ProcedureMaster $master, User $actor): array
    {
        return $this->transactional(function () use ($master, $actor): array {
            $lockedMaster = $this->lockMaster($master->getKey());

            if (! $lockedMaster->is_active) {
                return ['master' => $lockedMaster, 'changed' => false];
            }

            $before = $this->masterAuditSnapshot($lockedMaster);

            $lockedMaster->update(['is_active' => false]);
            $lockedMaster = $lockedMaster->fresh($this->masterRelations());

            $this->auditLogService->log(
                module: 'procedure_management',
                action: 'archive_master',
                auditable: $lockedMaster,
                description: sprintf('Procedure master %s diarsipkan oleh %s.', $lockedMaster->code, $actor->email),
                before: $before,
                after: $this->masterAuditSnapshot($lockedMaster),
                meta: ['actor_user_id' => $actor->getKey()],
            );

            return ['master' => $lockedMaster, 'changed' => true];
        });
    }

    public function createOrder(array $payload, User $actor): array
    {
        return $this->transactional(function () use ($payload, $actor): array {
            $visit = $this->resolveManagedVisit((int) $payload['visit_registration_id'], true);
            $master = $this->resolveManagedMaster((int) $payload['procedure_master_id']);
            $order = VisitProcedure::query()->create($this->orderAttributes($visit, $master, $payload, $actor));

            $this->syncVisit($visit);
            $order = $order->fresh($this->orderRelations());

            $this->auditLogService->log(
                module: 'procedure_management',
                action: 'create_order',
                auditable: $order,
                description: sprintf('Procedure order %s dibuat oleh %s.', $order->getKey(), $actor->email),
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

    public function updateOrder(VisitProcedure $order, array $payload, User $actor): array
    {
        return $this->transactional(function () use ($order, $payload, $actor): array {
            $lockedOrder = $this->lockOrder($order->getKey());

            if ((int) $payload['visit_registration_id'] !== (int) $lockedOrder->visit_registration_id) {
                throw new ProcedureManagementException(
                    'Procedure order yang sudah tercatat tidak boleh dipindahkan ke visit lain.',
                    409,
                    'visit_registration_id',
                );
            }

            $visit = $this->resolveManagedVisit((int) $lockedOrder->visit_registration_id, true);
            $master = $this->resolveManagedMaster((int) $payload['procedure_master_id']);

            if ($lockedOrder->isCancelled()) {
                if ($this->orderMatchesDesiredState($lockedOrder, $visit, $master, $payload, $actor)) {
                    return ['order' => $lockedOrder, 'changed' => false];
                }

                throw new ProcedureManagementException(
                    'Procedure order yang sudah cancelled tidak bisa diubah lagi.',
                    409,
                    'order',
                );
            }

            if ($lockedOrder->isCompleted()) {
                if ($this->orderMatchesDesiredState($lockedOrder, $visit, $master, $payload, $actor)) {
                    return ['order' => $lockedOrder, 'changed' => false];
                }

                throw new ProcedureManagementException(
                    'Procedure order yang sudah completed tidak bisa diubah lagi.',
                    409,
                    'order',
                );
            }

            if ($this->orderMatchesDesiredState($lockedOrder, $visit, $master, $payload, $actor)) {
                return ['order' => $lockedOrder, 'changed' => false];
            }

            $before = $this->orderAuditSnapshot($lockedOrder);

            $lockedOrder->fill($this->orderAttributes($visit, $master, $payload, $actor, $lockedOrder));
            $lockedOrder->save();
            $this->syncVisit($visit);
            $lockedOrder = $lockedOrder->fresh($this->orderRelations());

            $this->auditLogService->log(
                module: 'procedure_management',
                action: 'update_order',
                auditable: $lockedOrder,
                description: sprintf('Procedure order %s diperbarui oleh %s.', $lockedOrder->getKey(), $actor->email),
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

    public function cancelOrder(VisitProcedure $order, User $actor): array
    {
        return $this->transactional(function () use ($order, $actor): array {
            $lockedOrder = $this->lockOrder($order->getKey());

            if ($lockedOrder->isCancelled()) {
                return ['order' => $lockedOrder, 'changed' => false];
            }

            if ($lockedOrder->isCompleted()) {
                throw new ProcedureManagementException(
                    'Tindakan yang sudah completed tidak bisa dibatalkan. Ubah koreksi melalui proses terkontrol.',
                    409,
                    'procedure_order',
                );
            }

            $visit = $this->resolveManagedVisit((int) $lockedOrder->visit_registration_id, true);
            $before = $this->orderAuditSnapshot($lockedOrder);

            $lockedOrder->update([
                'status' => 'cancelled',
                'started_at' => null,
                'completed_at' => null,
                'cancelled_at' => $lockedOrder->cancelled_at ?? now(),
                'performed_by_user_id' => null,
                'performed_by_role' => null,
            ]);

            $this->syncVisit($visit);
            $lockedOrder = $lockedOrder->fresh($this->orderRelations());

            $this->auditLogService->log(
                module: 'procedure_management',
                action: 'cancel_order',
                auditable: $lockedOrder,
                description: sprintf('Procedure order %s dibatalkan oleh %s.', $lockedOrder->getKey(), $actor->email),
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

    public function masterPayload(ProcedureMaster $master): array
    {
        $master->loadMissing($this->masterRelations());

        return [
            'id' => $master->getKey(),
            'code' => $master->code,
            'name' => $master->name,
            'description' => $master->description,
            'performer_scope' => $master->performer_scope,
            'requires_doctor_order' => $master->requires_doctor_order,
            'default_fee' => (float) $master->default_fee,
            'is_active' => $master->is_active,
            'branch_prices' => $master->branchPrices->map(fn ($price): array => [
                'branch_id' => $price->branch_id,
                'branch_code' => $price->branch?->code,
                'price' => (float) $price->price,
                'is_active' => (bool) $price->is_active,
            ])->values()->all(),
        ];
    }

    public function orderPayload(VisitProcedure $order): array
    {
        $order->loadMissing($this->orderRelations());

        return [
            'id' => $order->getKey(),
            'visit_registration_id' => $order->visit_registration_id,
            'branch_id' => $order->branch_id,
            'branch_code' => $order->branch?->code,
            'section_id' => $order->section_id,
            'section_name' => $order->section?->name,
            'procedure_master_id' => $order->procedure_master_id,
            'procedure_master_name' => $order->procedureMaster?->name,
            'patient_name' => $order->visitRegistration?->patient?->full_name,
            'medical_record_no' => $order->visitRegistration?->patientBranchRecord?->medical_record_no,
            'ordered_by_doctor' => $order->orderedByDoctor?->displayName(),
            'performed_by_user' => $order->performedByUser?->name,
            'performed_by_role' => $order->performed_by_role,
            'quantity' => (float) $order->quantity,
            'unit_price' => (float) $order->unit_price,
            'subtotal' => (float) $order->subtotal,
            'status' => $order->status,
            'ordered_at' => $order->ordered_at?->toIso8601String(),
            'started_at' => $order->started_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'notes' => $order->notes,
        ];
    }

    private function transactional(callable $callback): mixed
    {
        try {
            return DB::transaction($callback);
        } catch (ProcedureManagementException $exception) {
            throw $exception;
        } catch (ValidationException $exception) {
            throw $this->mapValidationException($exception);
        }
    }

    private function mapValidationException(ValidationException $exception): ProcedureManagementException
    {
        $errors = $exception->errors();
        $key = (string) (array_key_first($errors) ?? 'procedure');
        $message = (string) ($errors[$key][0] ?? $exception->getMessage());

        return new ProcedureManagementException($message, 422, $key);
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
        return ProcedureMaster::query()
            ->with($this->masterRelations())
            ->searchForManagement($filters['search'])
            ->filterActiveState($filters['master_status'])
            ->orderByAllowed($filters['master_sort_by'], $filters['master_sort_direction'], self::MASTER_ALLOWED_SORTS)
            ->paginate($filters['master_per_page'], ['*'], 'master_page')
            ->withQueryString();
    }

    private function orderTable(array $filters): LengthAwarePaginator
    {
        return VisitProcedure::query()
            ->with($this->orderRelations())
            ->searchForManagement($filters['search'])
            ->filterBranch($filters['branch'])
            ->filterDate($filters['date'])
            ->filterStatus($filters['status'])
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
                'medicalRecord:id,visit_registration_id,status,doctor_id',
                'medicalRecord.doctor:id,full_name,title_prefix,title_suffix',
            ])
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
            throw new ProcedureManagementException('Visit registration tidak ditemukan.', 404, 'visit_registration_id');
        }

        if (! $visit->medicalRecord) {
            throw new ProcedureManagementException(
                'Tindakan hanya bisa dibuat untuk visit yang sudah punya medical record.',
                422,
                'visit_registration_id',
            );
        }

        if (in_array($visit->care_stage, ['cancelled', 'completed'], true)) {
            throw new ProcedureManagementException(
                'Visit ini sudah selesai atau dibatalkan.',
                409,
                'visit_registration_id',
            );
        }

        return $visit;
    }

    private function resolveManagedMaster(int $masterId): ProcedureMaster
    {
        $master = ProcedureMaster::query()->with('branchPrices')->find($masterId);

        if ($master === null) {
            throw new ProcedureManagementException('Procedure master tidak ditemukan.', 404, 'procedure_master_id');
        }

        return $master;
    }

    private function lockMaster(int $masterId): ProcedureMaster
    {
        $master = ProcedureMaster::query()
            ->with($this->masterRelations())
            ->lockForUpdate()
            ->find($masterId);

        if ($master === null) {
            throw new ProcedureManagementException('Procedure master tidak ditemukan.', 404, 'master');
        }

        return $master;
    }

    private function lockOrder(int $orderId): VisitProcedure
    {
        $order = VisitProcedure::query()
            ->with($this->orderRelations())
            ->lockForUpdate()
            ->find($orderId);

        if ($order === null) {
            throw new ProcedureManagementException('Procedure order tidak ditemukan.', 404, 'order');
        }

        return $order;
    }

    private function masterAttributes(array $payload): array
    {
        return [
            'code' => strtoupper(trim($payload['code'])),
            'name' => trim($payload['name']),
            'description' => $payload['description'] ?? null,
            'performer_scope' => $payload['performer_scope'],
            'requires_doctor_order' => (bool) $payload['requires_doctor_order'],
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

    private function masterMatchesDesiredState(ProcedureMaster $master, array $payload): bool
    {
        $attributes = $this->masterAttributes($payload);
        $currentPrices = $master->branchPrices
            ->mapWithKeys(fn ($price): array => [(string) $price->branch_id => (float) $price->price])
            ->all();

        return $master->code === $attributes['code']
            && $master->name === $attributes['name']
            && $master->description === $attributes['description']
            && $master->performer_scope === $attributes['performer_scope']
            && (bool) $master->requires_doctor_order === (bool) $attributes['requires_doctor_order']
            && (float) $master->default_fee === (float) $attributes['default_fee']
            && (bool) $master->is_active === (bool) $attributes['is_active']
            && $currentPrices === array_filter(
                $this->normalizedBranchPrices($payload['branch_prices'] ?? []),
                fn ($price) => $price !== null
            );
    }

    private function syncBranchPrices(ProcedureMaster $master, array $branchPrices): void
    {
        $normalized = $this->normalizedBranchPrices($branchPrices);

        foreach (Branch::query()->pluck('id') as $branchId) {
            $price = $normalized[(string) $branchId] ?? null;

            if ($price === null) {
                $master->branchPrices()->where('branch_id', $branchId)->delete();
                continue;
            }

            $master->branchPrices()->updateOrCreate(
                ['branch_id' => $branchId],
                ['price' => $price, 'is_active' => true],
            );
        }
    }

    private function orderAttributes(
        VisitRegistration $visit,
        ProcedureMaster $master,
        array $payload,
        User $actor,
        ?VisitProcedure $existing = null,
    ): array {
        $this->assertOrderStatusAllowed($existing?->status, $payload['status']);
        $this->assertPerformerScopeAllowed($master, $payload['status'], $actor);

        if ($master->requires_doctor_order && ! ($visit->medicalRecord?->doctor_id ?? $visit->doctor_id)) {
            throw new ProcedureManagementException(
                'Tindakan ini memerlukan doctor order, tapi doctor visit belum terisi.',
                422,
                'procedure_master_id',
            );
        }

        $branchPrice = $master->branchPrices
            ->where('is_active', true)
            ->firstWhere('branch_id', $visit->branch_id);

        $quantity = (float) $payload['quantity'];
        $unitPrice = (float) ($branchPrice?->price ?? $master->default_fee);
        $statusPayload = $this->statusPayload($payload['status'], $existing, $actor);

        return array_merge([
            'visit_registration_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'procedure_master_id' => $master->id,
            'ordered_by_doctor_id' => $visit->medicalRecord?->doctor_id ?? $visit->doctor_id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $quantity * $unitPrice,
            'notes' => $payload['notes'] ?? null,
        ], $statusPayload);
    }

    private function orderMatchesDesiredState(
        VisitProcedure $order,
        VisitRegistration $visit,
        ProcedureMaster $master,
        array $payload,
        User $actor,
    ): bool {
        $attributes = $this->orderAttributes($visit, $master, $payload, $actor, $order);

        return (int) $order->visit_registration_id === (int) $attributes['visit_registration_id']
            && (int) $order->branch_id === (int) $attributes['branch_id']
            && (int) $order->section_id === (int) $attributes['section_id']
            && (int) $order->procedure_master_id === (int) $attributes['procedure_master_id']
            && (int) ($order->ordered_by_doctor_id ?? 0) === (int) ($attributes['ordered_by_doctor_id'] ?? 0)
            && (float) $order->quantity === (float) $attributes['quantity']
            && (float) $order->unit_price === (float) $attributes['unit_price']
            && (float) $order->subtotal === (float) $attributes['subtotal']
            && $order->status === $attributes['status']
            && $order->notes === $attributes['notes']
            && $order->started_at?->equalTo($attributes['started_at'])
            && $order->completed_at?->equalTo($attributes['completed_at'])
            && $order->cancelled_at?->equalTo($attributes['cancelled_at'])
            && (int) ($order->performed_by_user_id ?? 0) === (int) ($attributes['performed_by_user_id'] ?? 0)
            && (string) ($order->performed_by_role ?? '') === (string) ($attributes['performed_by_role'] ?? '');
    }

    private function assertOrderStatusAllowed(?string $currentStatus, string $targetStatus): void
    {
        if ($currentStatus === null) {
            return;
        }

        $allowedTransitions = [
            'ordered' => ['ordered', 'in_progress', 'completed', 'cancelled'],
            'in_progress' => ['in_progress', 'completed', 'cancelled'],
            'completed' => ['completed'],
            'cancelled' => ['cancelled'],
        ];

        if (! in_array($targetStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            throw new ProcedureManagementException(
                'Perubahan status procedure order tidak valid untuk kondisi saat ini.',
                409,
                'status',
            );
        }
    }

    private function assertPerformerScopeAllowed(ProcedureMaster $master, string $status, User $actor): void
    {
        if (! in_array($status, ['in_progress', 'completed'], true)) {
            return;
        }

        $roles = $actor->getRoleNames()->map(fn ($role) => strtolower((string) $role));
        $isAdminOverride = $roles->contains(fn ($role) => in_array($role, ['super-admin', 'clinic-admin'], true));

        if ($isAdminOverride) {
            return;
        }

        $allowed = match ($master->performer_scope) {
            'doctor_only' => $roles->contains('doctor'),
            'nurse_only' => $roles->contains('nurse'),
            default => $roles->contains(fn ($role) => in_array($role, ['doctor', 'nurse'], true)),
        };

        if (! $allowed) {
            throw new ProcedureManagementException(
                'Role user saat ini tidak sesuai dengan performer scope tindakan.',
                403,
                'status',
            );
        }
    }

    private function statusPayload(string $status, ?VisitProcedure $existing, User $actor): array
    {
        $orderedAt = $existing?->ordered_at ?? now();

        return match ($status) {
            'ordered' => [
                'status' => 'ordered',
                'ordered_at' => $orderedAt,
                'started_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'performed_by_user_id' => null,
                'performed_by_role' => null,
            ],
            'in_progress' => [
                'status' => 'in_progress',
                'ordered_at' => $orderedAt,
                'started_at' => $existing?->started_at ?? now(),
                'completed_at' => null,
                'cancelled_at' => null,
                'performed_by_user_id' => $actor->getKey(),
                'performed_by_role' => $actor->getRoleNames()->first(),
            ],
            'completed' => [
                'status' => 'completed',
                'ordered_at' => $orderedAt,
                'started_at' => $existing?->started_at ?? now(),
                'completed_at' => $existing?->completed_at ?? now(),
                'cancelled_at' => null,
                'performed_by_user_id' => $actor->getKey(),
                'performed_by_role' => $actor->getRoleNames()->first(),
            ],
            'cancelled' => [
                'status' => 'cancelled',
                'ordered_at' => $orderedAt,
                'started_at' => null,
                'completed_at' => null,
                'cancelled_at' => $existing?->cancelled_at ?? now(),
                'performed_by_user_id' => null,
                'performed_by_role' => null,
            ],
            default => throw new ProcedureManagementException('Status procedure order tidak dikenali.', 422, 'status'),
        };
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
            'create' => $user?->hasRole('super-admin') || ($user?->can('create procedure management') ?? false),
            'edit' => $user?->hasRole('super-admin') || ($user?->can('edit procedure management') ?? false),
            'delete' => $user?->hasRole('super-admin') || ($user?->can('delete procedure management') ?? false),
        ];
    }

    private function masterAuditSnapshot(ProcedureMaster $master): array
    {
        $master->loadMissing($this->masterRelations());

        return [
            'id' => $master->getKey(),
            'code' => $master->code,
            'name' => $master->name,
            'description' => $master->description,
            'performer_scope' => $master->performer_scope,
            'requires_doctor_order' => $master->requires_doctor_order,
            'default_fee' => (float) $master->default_fee,
            'is_active' => $master->is_active,
            'branch_prices' => $master->branchPrices->map(fn ($price): array => [
                'branch_id' => $price->branch_id,
                'price' => (float) $price->price,
                'is_active' => (bool) $price->is_active,
            ])->values()->all(),
        ];
    }

    private function orderAuditSnapshot(VisitProcedure $order): array
    {
        $order->loadMissing($this->orderRelations());

        return [
            'id' => $order->getKey(),
            'visit_registration_id' => $order->visit_registration_id,
            'branch_id' => $order->branch_id,
            'section_id' => $order->section_id,
            'procedure_master_id' => $order->procedure_master_id,
            'procedure_master_name' => $order->procedureMaster?->name,
            'ordered_by_doctor_id' => $order->ordered_by_doctor_id,
            'performed_by_user_id' => $order->performed_by_user_id,
            'performed_by_role' => $order->performed_by_role,
            'quantity' => (float) $order->quantity,
            'unit_price' => (float) $order->unit_price,
            'subtotal' => (float) $order->subtotal,
            'status' => $order->status,
            'ordered_at' => $order->ordered_at?->toIso8601String(),
            'started_at' => $order->started_at?->toIso8601String(),
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
            'procedureMaster:id,code,name,performer_scope,requires_doctor_order,default_fee,is_active',
            'orderedByDoctor:id,full_name,title_prefix,title_suffix',
            'performedByUser:id,name',
        ];
    }
}
