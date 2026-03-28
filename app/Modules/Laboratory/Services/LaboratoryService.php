<?php

namespace App\Modules\Laboratory\Services;

use App\Models\Branch;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryTest;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Modules\Laboratory\Exceptions\LaboratoryManagementException;
use App\Services\AuditLogService;
use App\Services\ClinicalBillingService;
use App\Services\ClinicalWorkflowService;
use App\Services\NotificationCenterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LaboratoryService
{
    private const TEST_ALLOWED_SORTS = [
        'code',
        'name',
        'diagnostic_category',
        'created_at',
    ];

    private const ORDER_ALLOWED_SORTS = [
        'ordered_at',
        'created_at',
        'status',
        'unit_price',
    ];

    public function __construct(
        private readonly ClinicalBillingService $clinicalBillingService,
        private readonly ClinicalWorkflowService $clinicalWorkflowService,
        private readonly AuditLogService $auditLogService,
        private readonly NotificationCenterService $notificationCenterService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);

        return [
            'filters' => $filters,
            'tests' => $this->testTable($filters),
            'orders' => $this->orderTable($filters),
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'name', 'code']),
            'visitOptions' => $this->visitOptions(),
            'testOptions' => LaboratoryTest::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'diagnostic_category', 'sample_type', 'default_provider_type', 'result_entry_mode']),
            'abilities' => $this->abilities(),
            'testSortOptions' => [
                'name' => 'Nama test',
                'code' => 'Kode test',
                'diagnostic_category' => 'Kategori',
                'created_at' => 'Waktu dibuat',
            ],
            'orderSortOptions' => [
                'ordered_at' => 'Waktu order',
                'created_at' => 'Waktu dibuat',
                'status' => 'Status',
                'unit_price' => 'Harga',
            ],
            'perPageOptions' => [10, 25, 50, 100],
        ];
    }

    public function createTest(array $payload, User $actor): array
    {
        return $this->transactional(function () use ($payload, $actor): array {
            $normalizedCode = strtoupper(trim($payload['code']));
            $existing = LaboratoryTest::query()
                ->with($this->testRelations())
                ->where('code', $normalizedCode)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($this->testMatchesDesiredState($existing, $payload)) {
                    return ['test' => $existing, 'changed' => false];
                }

                throw new LaboratoryManagementException(
                    'Kode diagnostic test sudah dipakai oleh data lain.',
                    409,
                    'code',
                );
            }

            $test = LaboratoryTest::query()->create($this->testAttributes($payload));
            $this->syncParameters($test, $payload['parameter_lines'] ?? null);
            $this->syncBranchPrices($test, $payload['internal_prices'] ?? [], $payload['external_prices'] ?? []);
            $test = $test->fresh($this->testRelations());

            $this->auditLogService->log(
                module: 'laboratory_management',
                action: 'create_test',
                auditable: $test,
                description: sprintf('Diagnostic test %s dibuat oleh %s.', $test->code, $actor->email),
                after: $this->testAuditSnapshot($test),
                meta: ['actor_user_id' => $actor->getKey()],
            );

            return ['test' => $test, 'changed' => true];
        });
    }

    public function updateTest(LaboratoryTest $test, array $payload, User $actor): array
    {
        return $this->transactional(function () use ($test, $payload, $actor): array {
            $lockedTest = $this->lockTest($test->getKey());
            $normalizedCode = strtoupper(trim($payload['code']));

            if ($normalizedCode !== $lockedTest->code) {
                $duplicate = LaboratoryTest::query()
                    ->where('code', $normalizedCode)
                    ->whereKeyNot($lockedTest->getKey())
                    ->lockForUpdate()
                    ->exists();

                if ($duplicate) {
                    throw new LaboratoryManagementException(
                        'Kode diagnostic test sudah dipakai oleh data lain.',
                        409,
                        'code',
                    );
                }
            }

            if ($this->testMatchesDesiredState($lockedTest, $payload)) {
                return ['test' => $lockedTest, 'changed' => false];
            }

            $before = $this->testAuditSnapshot($lockedTest);

            $lockedTest->fill($this->testAttributes($payload));
            $lockedTest->save();
            $this->syncParameters($lockedTest, $payload['parameter_lines'] ?? null);
            $this->syncBranchPrices($lockedTest, $payload['internal_prices'] ?? [], $payload['external_prices'] ?? []);
            $lockedTest = $lockedTest->fresh($this->testRelations());

            $this->auditLogService->log(
                module: 'laboratory_management',
                action: 'update_test',
                auditable: $lockedTest,
                description: sprintf('Diagnostic test %s diperbarui oleh %s.', $lockedTest->code, $actor->email),
                before: $before,
                after: $this->testAuditSnapshot($lockedTest),
                meta: ['actor_user_id' => $actor->getKey()],
            );

            return ['test' => $lockedTest, 'changed' => true];
        });
    }

    public function archiveTest(LaboratoryTest $test, User $actor): array
    {
        return $this->transactional(function () use ($test, $actor): array {
            $lockedTest = $this->lockTest($test->getKey());

            if (! $lockedTest->is_active) {
                return ['test' => $lockedTest, 'changed' => false];
            }

            $before = $this->testAuditSnapshot($lockedTest);

            $lockedTest->update(['is_active' => false]);
            $lockedTest = $lockedTest->fresh($this->testRelations());

            $this->auditLogService->log(
                module: 'laboratory_management',
                action: 'archive_test',
                auditable: $lockedTest,
                description: sprintf('Diagnostic test %s diarsipkan oleh %s.', $lockedTest->code, $actor->email),
                before: $before,
                after: $this->testAuditSnapshot($lockedTest),
                meta: ['actor_user_id' => $actor->getKey()],
            );

            return ['test' => $lockedTest, 'changed' => true];
        });
    }

    public function createOrder(array $payload, User $actor): array
    {
        return $this->transactional(function () use ($payload, $actor): array {
            $visit = $this->resolveManagedVisit((int) $payload['visit_registration_id'], true);
            $test = $this->resolveManagedTest((int) $payload['laboratory_test_id']);

            $order = LaboratoryOrder::query()->create(
                $this->orderAttributes($visit, $test, $payload, $actor)
            );

            $this->syncResults($order, $test, $payload['result_lines'] ?? null);
            $this->syncVisit($visit);
            $order = $order->fresh($this->orderRelations());
            $this->notifyReviewedIfNeeded($order, null);

            $this->auditLogService->log(
                module: 'laboratory_management',
                action: 'create_order',
                auditable: $order,
                description: sprintf('Diagnostic order %s dibuat oleh %s.', $order->getKey(), $actor->email),
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

    public function updateOrder(LaboratoryOrder $order, array $payload, User $actor): array
    {
        return $this->transactional(function () use ($order, $payload, $actor): array {
            $lockedOrder = $this->lockOrder($order->getKey());

            if ((int) $payload['visit_registration_id'] !== (int) $lockedOrder->visit_registration_id) {
                throw new LaboratoryManagementException(
                    'Diagnostic order yang sudah tercatat tidak boleh dipindahkan ke visit lain.',
                    409,
                    'visit_registration_id',
                );
            }

            $visit = $this->resolveManagedVisit((int) $lockedOrder->visit_registration_id, true);
            $allowInactiveTest = (int) $lockedOrder->laboratory_test_id === (int) $payload['laboratory_test_id'];
            $test = $this->resolveManagedTest((int) $payload['laboratory_test_id'], $allowInactiveTest);

            if ($lockedOrder->isCancelled()) {
                if ($this->orderMatchesDesiredState($lockedOrder, $visit, $test, $payload, $actor)) {
                    return ['order' => $lockedOrder, 'changed' => false];
                }

                throw new LaboratoryManagementException(
                    'Diagnostic order yang sudah cancelled tidak bisa diubah lagi.',
                    409,
                    'order',
                );
            }

            if ($lockedOrder->isReviewed()) {
                if ($this->orderMatchesDesiredState($lockedOrder, $visit, $test, $payload, $actor)) {
                    return ['order' => $lockedOrder, 'changed' => false];
                }

                throw new LaboratoryManagementException(
                    'Diagnostic order yang sudah reviewed tidak bisa diubah lagi.',
                    409,
                    'order',
                );
            }

            if ($this->orderMatchesDesiredState($lockedOrder, $visit, $test, $payload, $actor)) {
                return ['order' => $lockedOrder, 'changed' => false];
            }

            $previousStatus = $lockedOrder->status;
            $before = $this->orderAuditSnapshot($lockedOrder);

            $lockedOrder->fill($this->orderAttributes($visit, $test, $payload, $actor, $lockedOrder));
            $lockedOrder->save();
            $this->syncResults($lockedOrder, $test, $payload['result_lines'] ?? null);
            $this->syncVisit($visit);
            $lockedOrder = $lockedOrder->fresh($this->orderRelations());
            $this->notifyReviewedIfNeeded($lockedOrder, $previousStatus);

            $this->auditLogService->log(
                module: 'laboratory_management',
                action: 'update_order',
                auditable: $lockedOrder,
                description: sprintf('Diagnostic order %s diperbarui oleh %s.', $lockedOrder->getKey(), $actor->email),
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

    public function cancelOrder(LaboratoryOrder $order, User $actor): array
    {
        return $this->transactional(function () use ($order, $actor): array {
            $lockedOrder = $this->lockOrder($order->getKey());

            if ($lockedOrder->isCancelled()) {
                return ['order' => $lockedOrder, 'changed' => false];
            }

            if (in_array($lockedOrder->status, ['resulted', 'reviewed'], true)) {
                throw new LaboratoryManagementException(
                    'Diagnostic order yang sudah memiliki hasil tidak bisa dibatalkan. Gunakan koreksi yang terkontrol.',
                    409,
                    'laboratory_order',
                );
            }

            $visit = $this->resolveManagedVisit((int) $lockedOrder->visit_registration_id, true);
            $before = $this->orderAuditSnapshot($lockedOrder);

            $lockedOrder->update([
                'status' => 'cancelled',
                'sample_collected_at' => null,
                'sent_to_partner_at' => null,
                'resulted_at' => null,
                'resulted_by_user_id' => null,
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
            ]);
            $lockedOrder->results()->delete();

            $this->syncVisit($visit);
            $lockedOrder = $lockedOrder->fresh($this->orderRelations());

            $this->auditLogService->log(
                module: 'laboratory_management',
                action: 'cancel_order',
                auditable: $lockedOrder,
                description: sprintf('Diagnostic order %s dibatalkan oleh %s.', $lockedOrder->getKey(), $actor->email),
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

    public function printRequest(LaboratoryOrder $order, User $actor): array
    {
        return $this->transactional(function () use ($order, $actor): array {
            $lockedOrder = $this->lockOrder($order->getKey());

            if ($lockedOrder->isCancelled()) {
                throw new LaboratoryManagementException(
                    'Order diagnostics yang dibatalkan tidak bisa dicetak sebagai request.',
                    409,
                    'laboratory_order',
                );
            }

            if ($lockedOrder->request_printed_at !== null) {
                return ['order' => $lockedOrder->fresh($this->printRelations()), 'changed' => false];
            }

            $before = $this->orderAuditSnapshot($lockedOrder);
            $lockedOrder->update(['request_printed_at' => now()]);
            $lockedOrder = $lockedOrder->fresh($this->printRelations());

            $this->auditLogService->log(
                module: 'laboratory_management',
                action: 'print_request',
                auditable: $lockedOrder,
                description: sprintf('Diagnostic request %s dibuka untuk cetak oleh %s.', $lockedOrder->getKey(), $actor->email),
                before: $before,
                after: $this->orderAuditSnapshot($lockedOrder),
                meta: ['actor_user_id' => $actor->getKey()],
            );

            return ['order' => $lockedOrder, 'changed' => true];
        });
    }

    public function printResult(LaboratoryOrder $order, User $actor): array
    {
        return $this->transactional(function () use ($order, $actor): array {
            $lockedOrder = $this->lockOrder($order->getKey());

            if ($lockedOrder->status !== 'reviewed') {
                throw new LaboratoryManagementException(
                    'Hasil diagnostics hanya bisa dicetak setelah status reviewed.',
                    409,
                    'laboratory_order',
                );
            }

            if ($lockedOrder->result_printed_at !== null) {
                return ['order' => $lockedOrder->fresh($this->printRelations()), 'changed' => false];
            }

            $before = $this->orderAuditSnapshot($lockedOrder);
            $lockedOrder->update(['result_printed_at' => now()]);
            $lockedOrder = $lockedOrder->fresh($this->printRelations());

            $this->auditLogService->log(
                module: 'laboratory_management',
                action: 'print_result',
                auditable: $lockedOrder,
                description: sprintf('Diagnostic result %s dibuka untuk cetak oleh %s.', $lockedOrder->getKey(), $actor->email),
                before: $before,
                after: $this->orderAuditSnapshot($lockedOrder),
                meta: ['actor_user_id' => $actor->getKey()],
            );

            return ['order' => $lockedOrder, 'changed' => true];
        });
    }

    public function testPayload(LaboratoryTest $test): array
    {
        $test->loadMissing($this->testRelations());

        return [
            'id' => $test->getKey(),
            'code' => $test->code,
            'name' => $test->name,
            'diagnostic_category' => $test->diagnostic_category,
            'sample_type' => $test->sample_type,
            'default_provider_type' => $test->default_provider_type,
            'result_entry_mode' => $test->result_entry_mode,
            'description' => $test->description,
            'is_active' => $test->is_active,
            'parameter_lines' => $test->parameters
                ->map(fn ($parameter): string => implode('|', array_filter([
                    $parameter->code,
                    $parameter->name,
                    $parameter->unit,
                    $parameter->reference_range,
                ], fn ($value) => $value !== null && $value !== '')))
                ->implode("\n"),
            'parameters' => $test->parameters->map(fn ($parameter): array => [
                'code' => $parameter->code,
                'name' => $parameter->name,
                'unit' => $parameter->unit,
                'reference_range' => $parameter->reference_range,
                'sort_order' => $parameter->sort_order,
                'is_active' => $parameter->is_active,
            ])->values()->all(),
            'branch_prices' => $test->branchPrices->map(fn ($price): array => [
                'branch_id' => $price->branch_id,
                'branch_code' => $price->branch?->code,
                'internal_price' => $price->internal_price !== null ? (float) $price->internal_price : null,
                'external_price' => $price->external_price !== null ? (float) $price->external_price : null,
                'is_active' => (bool) $price->is_active,
            ])->values()->all(),
        ];
    }

    public function orderPayload(LaboratoryOrder $order): array
    {
        $order->loadMissing($this->orderRelations());

        return [
            'id' => $order->getKey(),
            'visit_registration_id' => $order->visit_registration_id,
            'branch_id' => $order->branch_id,
            'branch_code' => $order->branch?->code,
            'section_id' => $order->section_id,
            'section_name' => $order->section?->name,
            'laboratory_test_id' => $order->laboratory_test_id,
            'laboratory_test_name' => $order->laboratoryTest?->name,
            'diagnostic_category' => $order->laboratoryTest?->diagnostic_category,
            'patient_name' => $order->visitRegistration?->patient?->full_name,
            'medical_record_no' => $order->visitRegistration?->patientBranchRecord?->medical_record_no,
            'provider_type' => $order->provider_type,
            'partner_name' => $order->partner_name,
            'external_reference_no' => $order->external_reference_no,
            'status' => $order->status,
            'unit_price' => (float) $order->unit_price,
            'ordered_at' => $order->ordered_at?->toIso8601String(),
            'sample_collected_at' => $order->sample_collected_at?->toIso8601String(),
            'sent_to_partner_at' => $order->sent_to_partner_at?->toIso8601String(),
            'resulted_at' => $order->resulted_at?->toIso8601String(),
            'reviewed_at' => $order->reviewed_at?->toIso8601String(),
            'resulted_by' => $order->resultedBy?->name,
            'reviewed_by' => $order->reviewedBy?->name,
            'result_attachment_path' => $order->result_attachment_path,
            'result_summary' => $order->result_summary,
            'result_impression' => $order->result_impression,
            'notes' => $order->notes,
            'request_printed_at' => $order->request_printed_at?->toIso8601String(),
            'result_printed_at' => $order->result_printed_at?->toIso8601String(),
            'results' => $order->results->map(fn ($result): array => [
                'parameter_code' => $result->parameter_code,
                'parameter_name' => $result->parameter_name,
                'value' => $result->value,
                'unit' => $result->unit,
                'reference_range' => $result->reference_range,
                'result_flag' => $result->result_flag,
                'notes' => $result->notes,
                'sort_order' => $result->sort_order,
            ])->values()->all(),
        ];
    }

    private function transactional(callable $callback): mixed
    {
        try {
            return DB::transaction($callback);
        } catch (LaboratoryManagementException $exception) {
            throw $exception;
        } catch (ValidationException $exception) {
            throw $this->mapValidationException($exception);
        }
    }

    private function mapValidationException(ValidationException $exception): LaboratoryManagementException
    {
        $errors = $exception->errors();
        $key = (string) (array_key_first($errors) ?? 'laboratory');
        $message = (string) ($errors[$key][0] ?? $exception->getMessage());

        return new LaboratoryManagementException($message, 422, $key);
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'search' => trim((string) ($filters['search'] ?? '')),
            'branch' => (string) ($filters['branch'] ?? ''),
            'status' => (string) ($filters['status'] ?? ''),
            'provider_type' => (string) ($filters['provider_type'] ?? ''),
            'date' => (string) ($filters['date'] ?? now()->toDateString()),
            'test_status' => (string) ($filters['test_status'] ?? ''),
            'test_sort_by' => (string) ($filters['test_sort_by'] ?? 'name'),
            'test_sort_direction' => (string) ($filters['test_sort_direction'] ?? 'asc'),
            'test_per_page' => (int) ($filters['test_per_page'] ?? 10),
            'order_sort_by' => (string) ($filters['order_sort_by'] ?? 'ordered_at'),
            'order_sort_direction' => (string) ($filters['order_sort_direction'] ?? 'desc'),
            'order_per_page' => (int) ($filters['order_per_page'] ?? 10),
        ];
    }

    private function testTable(array $filters): LengthAwarePaginator
    {
        return LaboratoryTest::query()
            ->with($this->testRelations())
            ->searchForManagement($filters['search'])
            ->filterActiveState($filters['test_status'])
            ->orderByAllowed($filters['test_sort_by'], $filters['test_sort_direction'], self::TEST_ALLOWED_SORTS)
            ->paginate($filters['test_per_page'], ['*'], 'test_page')
            ->withQueryString();
    }

    private function orderTable(array $filters): LengthAwarePaginator
    {
        return LaboratoryOrder::query()
            ->with($this->orderRelations())
            ->searchForManagement($filters['search'])
            ->filterBranch($filters['branch'])
            ->filterStatus($filters['status'])
            ->filterProviderType($filters['provider_type'])
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
        $query = VisitRegistration::query()->with('medicalRecord');

        if ($lock) {
            $query->lockForUpdate();
        }

        $visit = $query->find($visitRegistrationId);

        if ($visit === null) {
            throw new LaboratoryManagementException('Visit registration tidak ditemukan.', 404, 'visit_registration_id');
        }

        if (! $visit->medicalRecord) {
            throw new LaboratoryManagementException(
                'Diagnostic order hanya bisa dibuat untuk visit yang sudah punya medical record.',
                422,
                'visit_registration_id',
            );
        }

        if (in_array($visit->care_stage, ['cancelled', 'completed'], true)) {
            throw new LaboratoryManagementException(
                'Visit ini sudah selesai atau dibatalkan.',
                409,
                'visit_registration_id',
            );
        }

        return $visit;
    }

    private function resolveManagedTest(int $testId, bool $allowInactive = false): LaboratoryTest
    {
        $test = LaboratoryTest::query()->with($this->testRelations())->find($testId);

        if ($test === null) {
            throw new LaboratoryManagementException('Diagnostic test tidak ditemukan.', 404, 'laboratory_test_id');
        }

        if (! $allowInactive && ! $test->is_active) {
            throw new LaboratoryManagementException(
                'Diagnostic test yang tidak aktif tidak bisa dipakai untuk order baru.',
                409,
                'laboratory_test_id',
            );
        }

        return $test;
    }

    private function lockTest(int $testId): LaboratoryTest
    {
        $test = LaboratoryTest::query()
            ->with($this->testRelations())
            ->lockForUpdate()
            ->find($testId);

        if ($test === null) {
            throw new LaboratoryManagementException('Diagnostic test tidak ditemukan.', 404, 'test');
        }

        return $test;
    }

    private function lockOrder(int $orderId): LaboratoryOrder
    {
        $order = LaboratoryOrder::query()
            ->with($this->orderRelations())
            ->lockForUpdate()
            ->find($orderId);

        if ($order === null) {
            throw new LaboratoryManagementException('Diagnostic order tidak ditemukan.', 404, 'order');
        }

        return $order;
    }

    private function testAttributes(array $payload): array
    {
        return [
            'code' => strtoupper(trim($payload['code'])),
            'name' => trim($payload['name']),
            'diagnostic_category' => $payload['diagnostic_category'],
            'sample_type' => $payload['sample_type'] ?? null,
            'default_provider_type' => $payload['default_provider_type'],
            'result_entry_mode' => $payload['result_entry_mode'],
            'description' => $payload['description'] ?? null,
            'is_active' => (bool) $payload['is_active'],
        ];
    }

    private function normalizedPrices(array $prices): array
    {
        return collect($prices)
            ->mapWithKeys(fn ($value, $key): array => [(string) $key => $value === null || $value === '' ? null : (float) $value])
            ->all();
    }

    private function testMatchesDesiredState(LaboratoryTest $test, array $payload): bool
    {
        $attributes = $this->testAttributes($payload);
        $currentParameters = $test->parameters->map(fn ($parameter): string => implode('|', array_filter([
            $parameter->code,
            $parameter->name,
            $parameter->unit,
            $parameter->reference_range,
        ], fn ($value) => $value !== null && $value !== '')))->implode("\n");
        $currentInternalPrices = array_filter(
            $test->branchPrices->mapWithKeys(
                fn ($price): array => [(string) $price->branch_id => $price->internal_price !== null ? (float) $price->internal_price : null]
            )->all(),
            fn ($price) => $price !== null,
        );
        $currentExternalPrices = array_filter(
            $test->branchPrices->mapWithKeys(
                fn ($price): array => [(string) $price->branch_id => $price->external_price !== null ? (float) $price->external_price : null]
            )->all(),
            fn ($price) => $price !== null,
        );

        return $test->code === $attributes['code']
            && $test->name === $attributes['name']
            && $test->diagnostic_category === $attributes['diagnostic_category']
            && $test->sample_type === $attributes['sample_type']
            && $test->default_provider_type === $attributes['default_provider_type']
            && $test->result_entry_mode === $attributes['result_entry_mode']
            && $test->description === $attributes['description']
            && (bool) $test->is_active === (bool) $attributes['is_active']
            && $currentParameters === (string) ($payload['parameter_lines'] ?? '')
            && $currentInternalPrices === array_filter($this->normalizedPrices($payload['internal_prices'] ?? []), fn ($price) => $price !== null)
            && $currentExternalPrices === array_filter($this->normalizedPrices($payload['external_prices'] ?? []), fn ($price) => $price !== null);
    }

    private function syncParameters(LaboratoryTest $test, ?string $parameterLines): void
    {
        $test->parameters()->delete();

        if ($test->result_entry_mode === 'narrative') {
            return;
        }

        $rows = collect(preg_split('/\r\n|\r|\n/', (string) $parameterLines))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->map(function (string $line, int $index): array {
                [$code, $name, $unit, $range] = array_pad(array_map('trim', explode('|', $line)), 4, null);

                if (! $name) {
                    throw ValidationException::withMessages([
                        'parameter_lines' => 'Setiap baris parameter harus minimal punya nama. Format: CODE|NAME|UNIT|RANGE',
                    ]);
                }

                return [
                    'code' => $code ?: null,
                    'name' => $name,
                    'unit' => $unit ?: null,
                    'reference_range' => $range ?: null,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ];
            })
            ->all();

        if ($rows !== []) {
            $test->parameters()->createMany($rows);
        }
    }

    private function syncBranchPrices(LaboratoryTest $test, array $internalPrices, array $externalPrices): void
    {
        $normalizedInternalPrices = $this->normalizedPrices($internalPrices);
        $normalizedExternalPrices = $this->normalizedPrices($externalPrices);

        foreach (Branch::query()->pluck('id') as $branchId) {
            $internal = $normalizedInternalPrices[(string) $branchId] ?? null;
            $external = $normalizedExternalPrices[(string) $branchId] ?? null;

            if ($internal === null && $external === null) {
                $test->branchPrices()->where('branch_id', $branchId)->delete();
                continue;
            }

            $test->branchPrices()->updateOrCreate(
                ['branch_id' => $branchId],
                [
                    'internal_price' => $internal,
                    'external_price' => $external,
                    'is_active' => true,
                ],
            );
        }
    }

    private function orderAttributes(
        VisitRegistration $visit,
        LaboratoryTest $test,
        array $payload,
        User $actor,
        ?LaboratoryOrder $existing = null,
    ): array {
        $this->assertOrderStatusAllowed($existing?->status, $payload['status']);
        $this->assertProviderSpecificStatusAllowed($payload['provider_type'], $payload['status']);
        $this->assertDiagnosticResultPayload($test, $payload, $actor);

        if ($payload['provider_type'] === 'external' && blank($payload['partner_name'] ?? null)) {
            throw ValidationException::withMessages([
                'partner_name' => 'Partner lab wajib diisi untuk order external.',
            ]);
        }

        $branchPrice = $test->branchPrices->firstWhere('branch_id', $visit->branch_id);
        $unitPrice = $payload['provider_type'] === 'internal'
            ? (float) ($branchPrice?->internal_price ?? 0)
            : (float) ($branchPrice?->external_price ?? 0);

        $timestamps = $this->statusTimestamps($payload['status'], $existing, $actor);

        return array_merge([
            'visit_registration_id' => $visit->id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'laboratory_test_id' => $test->id,
            'ordered_by_doctor_id' => $existing?->ordered_by_doctor_id ?? ($visit->medicalRecord?->doctor_id ?? $visit->doctor_id),
            'provider_type' => $payload['provider_type'],
            'partner_name' => $payload['partner_name'] ?? null,
            'external_reference_no' => $payload['external_reference_no'] ?? null,
            'unit_price' => $unitPrice,
            'result_attachment_path' => $payload['result_attachment_path'] ?? null,
            'result_summary' => $payload['result_summary'] ?? null,
            'result_impression' => $payload['result_impression'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ], $timestamps);
    }

    private function statusTimestamps(string $status, ?LaboratoryOrder $existing, User $actor): array
    {
        $orderedAt = $existing?->ordered_at ?? now();
        $sampleCollectedAt = in_array($status, ['sample_collected', 'processing', 'resulted', 'reviewed'], true)
            ? ($existing?->sample_collected_at ?? now())
            : null;
        $sentToPartnerAt = in_array($status, ['sent_to_partner', 'resulted', 'reviewed'], true)
            ? ($existing?->sent_to_partner_at ?? now())
            : null;
        $resultedAt = in_array($status, ['resulted', 'reviewed'], true)
            ? ($existing?->resulted_at ?? now())
            : null;
        $reviewedAt = $status === 'reviewed'
            ? ($existing?->reviewed_at ?? now())
            : null;

        return [
            'status' => $status,
            'ordered_at' => $orderedAt,
            'sample_collected_at' => $sampleCollectedAt,
            'sent_to_partner_at' => $sentToPartnerAt,
            'resulted_at' => $resultedAt,
            'resulted_by_user_id' => $resultedAt ? ($existing?->resulted_by_user_id ?? $actor->getKey()) : null,
            'reviewed_at' => $reviewedAt,
            'reviewed_by_user_id' => $reviewedAt ? ($existing?->reviewed_by_user_id ?? $actor->getKey()) : null,
            'request_printed_at' => $existing?->request_printed_at,
            'result_printed_at' => $existing?->result_printed_at,
        ];
    }

    private function syncResults(LaboratoryOrder $order, LaboratoryTest $test, ?string $resultLines): void
    {
        $order->results()->delete();

        if ($test->result_entry_mode === 'narrative') {
            return;
        }

        $lines = collect(preg_split('/\r\n|\r|\n/', (string) $resultLines))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            return;
        }

        $rows = $lines->map(function (string $line, int $index): array {
            [$code, $name, $value, $unit, $range, $flag, $notes] = array_pad(array_map('trim', explode('|', $line)), 7, null);

            if (! $name) {
                throw ValidationException::withMessages([
                    'result_lines' => 'Setiap baris hasil diagnostics harus minimal punya nama parameter. Format: CODE|NAME|VALUE|UNIT|RANGE|FLAG|NOTES',
                ]);
            }

            return [
                'parameter_code' => $code ?: null,
                'parameter_name' => $name,
                'value' => $value ?: null,
                'unit' => $unit ?: null,
                'reference_range' => $range ?: null,
                'result_flag' => $flag ? Str::lower($flag) : null,
                'notes' => $notes ?: null,
                'sort_order' => ($index + 1) * 10,
            ];
        })->all();

        $order->results()->createMany($rows);
    }

    private function assertDiagnosticResultPayload(LaboratoryTest $test, array $payload, User $actor): void
    {
        $status = $payload['status'];

        if (! in_array($status, ['resulted', 'reviewed'], true)) {
            return;
        }

        if ($status === 'reviewed') {
            $this->ensureCanReview($actor);
        }

        $resultLines = trim((string) ($payload['result_lines'] ?? ''));
        $summary = trim((string) ($payload['result_summary'] ?? ''));
        $impression = trim((string) ($payload['result_impression'] ?? ''));
        $attachment = trim((string) ($payload['result_attachment_path'] ?? ''));

        $hasStructured = $resultLines !== '';
        $hasNarrative = $summary !== '' || $impression !== '' || $attachment !== '';

        if ($test->result_entry_mode === 'structured' && ! $hasStructured) {
            throw ValidationException::withMessages([
                'result_lines' => 'Hasil structured wajib diisi untuk test ini sebelum status resulted atau reviewed.',
            ]);
        }

        if ($test->result_entry_mode === 'narrative' && ! $hasNarrative) {
            throw ValidationException::withMessages([
                'result_summary' => 'Hasil narrative atau attachment wajib diisi untuk test ini sebelum status resulted atau reviewed.',
            ]);
        }

        if ($test->result_entry_mode === 'hybrid' && ! ($hasStructured || $hasNarrative)) {
            throw ValidationException::withMessages([
                'result_lines' => 'Minimal isi hasil structured atau narrative untuk test hybrid sebelum status resulted atau reviewed.',
            ]);
        }
    }

    private function orderMatchesDesiredState(
        LaboratoryOrder $order,
        VisitRegistration $visit,
        LaboratoryTest $test,
        array $payload,
        User $actor,
    ): bool {
        $attributes = $this->orderAttributes($visit, $test, $payload, $actor, $order);
        $currentResults = $order->results->map(fn ($result): string => implode('|', array_filter([
            $result->parameter_code,
            $result->parameter_name,
            $result->value,
            $result->unit,
            $result->reference_range,
            $result->result_flag,
            $result->notes,
        ], fn ($value) => $value !== null && $value !== '')))->implode("\n");

        return (int) $order->visit_registration_id === (int) $attributes['visit_registration_id']
            && (int) $order->branch_id === (int) $attributes['branch_id']
            && (int) ($order->section_id ?? 0) === (int) ($attributes['section_id'] ?? 0)
            && (int) $order->laboratory_test_id === (int) $attributes['laboratory_test_id']
            && (int) ($order->ordered_by_doctor_id ?? 0) === (int) ($attributes['ordered_by_doctor_id'] ?? 0)
            && (float) $order->unit_price === (float) $attributes['unit_price']
            && $order->provider_type === $attributes['provider_type']
            && $order->partner_name === $attributes['partner_name']
            && $order->external_reference_no === $attributes['external_reference_no']
            && $order->status === $attributes['status']
            && $order->result_attachment_path === $attributes['result_attachment_path']
            && $order->result_summary === $attributes['result_summary']
            && $order->result_impression === $attributes['result_impression']
            && $order->notes === $attributes['notes']
            && (int) ($order->resulted_by_user_id ?? 0) === (int) ($attributes['resulted_by_user_id'] ?? 0)
            && (int) ($order->reviewed_by_user_id ?? 0) === (int) ($attributes['reviewed_by_user_id'] ?? 0)
            && $order->sample_collected_at?->equalTo($attributes['sample_collected_at'])
            && $order->sent_to_partner_at?->equalTo($attributes['sent_to_partner_at'])
            && $order->resulted_at?->equalTo($attributes['resulted_at'])
            && $order->reviewed_at?->equalTo($attributes['reviewed_at'])
            && $currentResults === (string) ($payload['result_lines'] ?? '');
    }

    private function assertOrderStatusAllowed(?string $currentStatus, string $targetStatus): void
    {
        if ($currentStatus === null) {
            return;
        }

        $allowedTransitions = [
            'ordered' => ['ordered', 'sample_collected', 'processing', 'sent_to_partner', 'resulted', 'reviewed', 'cancelled'],
            'sample_collected' => ['sample_collected', 'processing', 'resulted', 'reviewed', 'cancelled'],
            'processing' => ['processing', 'resulted', 'reviewed', 'cancelled'],
            'sent_to_partner' => ['sent_to_partner', 'resulted', 'reviewed', 'cancelled'],
            'resulted' => ['resulted', 'reviewed'],
            'reviewed' => ['reviewed'],
            'cancelled' => ['cancelled'],
        ];

        if (! in_array($targetStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            throw new LaboratoryManagementException(
                'Perubahan status diagnostics order tidak valid untuk kondisi saat ini.',
                409,
                'status',
            );
        }
    }

    private function assertProviderSpecificStatusAllowed(string $providerType, string $targetStatus): void
    {
        $internalAllowed = ['ordered', 'sample_collected', 'processing', 'resulted', 'reviewed', 'cancelled'];
        $externalAllowed = ['ordered', 'sent_to_partner', 'resulted', 'reviewed', 'cancelled'];

        $allowed = $providerType === 'internal' ? $internalAllowed : $externalAllowed;

        if (! in_array($targetStatus, $allowed, true)) {
            throw new LaboratoryManagementException(
                'Status diagnostics order tidak valid untuk provider type yang dipilih.',
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
            'create' => $user?->hasRole('super-admin') || ($user?->can('create laboratory management') ?? false),
            'edit' => $user?->hasRole('super-admin') || ($user?->can('edit laboratory management') ?? false),
            'delete' => $user?->hasRole('super-admin') || ($user?->can('delete laboratory management') ?? false),
            'print' => $user?->hasRole('super-admin') || ($user?->can('view laboratory management') ?? false),
        ];
    }

    private function ensureCanReview(User $actor): void
    {
        if (! $actor->hasAnyRole(['super-admin', 'clinic-admin', 'doctor'])) {
            throw ValidationException::withMessages([
                'status' => 'Status reviewed hanya bisa disimpan oleh dokter atau admin klinik.',
            ]);
        }
    }

    private function notifyReviewedIfNeeded(LaboratoryOrder $order, ?string $previousStatus): void
    {
        if ($order->status !== 'reviewed' || $previousStatus === 'reviewed') {
            return;
        }

        $patientName = $order->visitRegistration?->patient?->full_name ?? 'Patient';
        $medicalRecordNo = $order->visitRegistration?->patientBranchRecord?->medical_record_no ?? '-';
        $testName = $order->laboratoryTest?->name ?? 'Diagnostic Test';

        $this->notificationCenterService->notifyRoles(
            ['super-admin', 'clinic-admin', 'front-office', 'cashier'],
            [
                'title' => 'Diagnostic result reviewed',
                'message' => sprintf('%s (%s) untuk %s sudah reviewed dan siap dicetak.', $testName, $medicalRecordNo, $patientName),
                'action_url' => route('laboratory') . '?search=' . urlencode($medicalRecordNo),
                'action_label' => 'Open diagnostics',
                'module' => 'laboratory',
                'level' => 'success',
                'meta' => [
                    'laboratory_order_id' => $order->id,
                    'branch_id' => $order->branch_id,
                ],
            ],
        );
    }

    private function testAuditSnapshot(LaboratoryTest $test): array
    {
        $test->loadMissing($this->testRelations());

        return [
            'id' => $test->getKey(),
            'code' => $test->code,
            'name' => $test->name,
            'diagnostic_category' => $test->diagnostic_category,
            'sample_type' => $test->sample_type,
            'default_provider_type' => $test->default_provider_type,
            'result_entry_mode' => $test->result_entry_mode,
            'description' => $test->description,
            'is_active' => $test->is_active,
            'parameters' => $test->parameters->map(fn ($parameter): array => [
                'code' => $parameter->code,
                'name' => $parameter->name,
                'unit' => $parameter->unit,
                'reference_range' => $parameter->reference_range,
                'sort_order' => $parameter->sort_order,
                'is_active' => $parameter->is_active,
            ])->values()->all(),
            'branch_prices' => $test->branchPrices->map(fn ($price): array => [
                'branch_id' => $price->branch_id,
                'internal_price' => $price->internal_price !== null ? (float) $price->internal_price : null,
                'external_price' => $price->external_price !== null ? (float) $price->external_price : null,
                'is_active' => (bool) $price->is_active,
            ])->values()->all(),
        ];
    }

    private function orderAuditSnapshot(LaboratoryOrder $order): array
    {
        $order->loadMissing($this->orderRelations());

        return [
            'id' => $order->getKey(),
            'visit_registration_id' => $order->visit_registration_id,
            'branch_id' => $order->branch_id,
            'section_id' => $order->section_id,
            'laboratory_test_id' => $order->laboratory_test_id,
            'ordered_by_doctor_id' => $order->ordered_by_doctor_id,
            'provider_type' => $order->provider_type,
            'partner_name' => $order->partner_name,
            'external_reference_no' => $order->external_reference_no,
            'status' => $order->status,
            'unit_price' => (float) $order->unit_price,
            'ordered_at' => $order->ordered_at?->toIso8601String(),
            'sample_collected_at' => $order->sample_collected_at?->toIso8601String(),
            'sent_to_partner_at' => $order->sent_to_partner_at?->toIso8601String(),
            'resulted_at' => $order->resulted_at?->toIso8601String(),
            'resulted_by_user_id' => $order->resulted_by_user_id,
            'reviewed_at' => $order->reviewed_at?->toIso8601String(),
            'reviewed_by_user_id' => $order->reviewed_by_user_id,
            'request_printed_at' => $order->request_printed_at?->toIso8601String(),
            'result_printed_at' => $order->result_printed_at?->toIso8601String(),
            'result_attachment_path' => $order->result_attachment_path,
            'result_summary' => $order->result_summary,
            'result_impression' => $order->result_impression,
            'notes' => $order->notes,
            'results' => $order->results->map(fn ($result): array => [
                'parameter_code' => $result->parameter_code,
                'parameter_name' => $result->parameter_name,
                'value' => $result->value,
                'unit' => $result->unit,
                'reference_range' => $result->reference_range,
                'result_flag' => $result->result_flag,
                'notes' => $result->notes,
                'sort_order' => $result->sort_order,
            ])->values()->all(),
        ];
    }

    private function testRelations(): array
    {
        return [
            'parameters',
            'branchPrices.branch:id,name,code',
        ];
    }

    private function orderRelations(): array
    {
        return [
            'visitRegistration.patient:id,full_name,phone',
            'visitRegistration.patientBranchRecord:id,medical_record_no',
            'visitRegistration.branch:id,name,code,clinic_id',
            'visitRegistration.branch.clinic:id,name',
            'visitRegistration.section:id,name,code',
            'laboratoryTest:id,code,name,diagnostic_category,sample_type,result_entry_mode',
            'orderedByDoctor:id,full_name,title_prefix,title_suffix',
            'resultedBy:id,name',
            'reviewedBy:id,name',
            'results',
            'branch:id,name,code',
            'section:id,name,code',
        ];
    }

    private function printRelations(): array
    {
        return [
            'visitRegistration.patient',
            'visitRegistration.patientBranchRecord',
            'visitRegistration.branch.clinic',
            'visitRegistration.branch',
            'visitRegistration.section',
            'orderedByDoctor',
            'laboratoryTest.parameters',
            'resultedBy',
            'reviewedBy',
            'results',
        ];
    }
}
