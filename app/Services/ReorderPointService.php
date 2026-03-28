<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineReorderPolicy;
use App\Models\MedicineUnit;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReorderPointService
{
    public function __construct(
        private readonly NotificationCenterService $notificationCenterService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function getModuleData(array $filters): array
    {
        $normalized = $this->normalizeFilters($filters);

        $policies = $this->policyTable($normalized);
        $lowStockRecommendations = $this->lowStockRecommendations($normalized, 8);

        return [
            'reorderFilters' => $normalized,
            'reorderPolicies' => $policies,
            'lowStockRecommendations' => $lowStockRecommendations,
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'code', 'name']),
            'medicineOptions' => Medicine::query()
                ->with(['units' => fn ($query) => $query
                    ->where('is_active', true)
                    ->where('allow_purchase', true)
                    ->orderBy('sort_order')
                    ->orderBy('conversion_factor')])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'generic_name', 'base_unit']),
            'supplierOptions' => Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'reorderMetrics' => [
                'active_policies' => MedicineReorderPolicy::query()->where('is_active', true)->count(),
                'branches_covered' => MedicineReorderPolicy::query()
                    ->where('is_active', true)
                    ->distinct('branch_id')
                    ->count('branch_id'),
                'low_stock_items' => $this->lowStockCount($normalized),
            ],
            'abilities' => $this->abilities(),
        ];
    }

    public function createPolicy(array $payload): MedicineReorderPolicy
    {
        $medicine = Medicine::query()
            ->with(['units' => fn ($query) => $query->where('is_active', true)])
            ->findOrFail($payload['medicine_id']);

        $policy = MedicineReorderPolicy::query()->create($this->policyAttributes($payload, $medicine));
        $this->syncSuppliers($policy, $payload['supplier_preferences'] ?? []);
        $this->refreshForMedicineBranch((int) $policy->medicine_id, (int) $policy->branch_id);

        $fresh = $policy->fresh($this->relations());

        $this->auditLogService->log(
            'reorder_points',
            'created',
            $fresh,
            'Reorder point policy baru dibuat.',
            [],
            $fresh?->toArray() ?? [],
        );

        return $fresh;
    }

    public function updatePolicy(MedicineReorderPolicy $policy, array $payload): MedicineReorderPolicy
    {
        $before = $policy->fresh($this->relations())?->toArray() ?? [];
        $medicine = Medicine::query()
            ->with(['units' => fn ($query) => $query->where('is_active', true)])
            ->findOrFail($payload['medicine_id']);

        $policy->update($this->policyAttributes($payload, $medicine));
        $this->syncSuppliers($policy, $payload['supplier_preferences'] ?? []);
        $this->refreshForMedicineBranch((int) $policy->medicine_id, (int) $policy->branch_id);

        $fresh = $policy->fresh($this->relations());

        $this->auditLogService->log(
            'reorder_points',
            'updated',
            $fresh,
            'Reorder point policy diperbarui.',
            $before,
            $fresh?->toArray() ?? [],
        );

        return $fresh;
    }

    public function archivePolicy(MedicineReorderPolicy $policy): void
    {
        $before = $policy->fresh($this->relations())?->toArray() ?? [];

        $policy->update([
            'is_active' => false,
        ]);

        $fresh = $policy->fresh($this->relations());

        $this->auditLogService->log(
            'reorder_points',
            'archived',
            $fresh,
            'Reorder point policy diarsipkan.',
            $before,
            $fresh?->toArray() ?? [],
        );
    }

    public function refreshForMedicineBranch(int $medicineId, int $branchId): void
    {
        $policy = MedicineReorderPolicy::query()
            ->with($this->relations())
            ->where('medicine_id', $medicineId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();

        if (! $policy) {
            return;
        }

        $recommendation = $this->decoratePolicy($policy);

        if (! $recommendation['is_low_stock']) {
            return;
        }

        $eventKey = sprintf('reorder:%s:%s', $branchId, $medicineId);
        $users = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['clinic-admin', 'pharmacist']))
            ->get()
            ->filter(function (User $user) use ($eventKey): bool {
                return ! $user->unreadNotifications()
                    ->get()
                    ->contains(fn ($notification): bool => data_get($notification->data, 'event_key') === $eventKey);
            });

        $supplierLabel = collect($recommendation['suppliers'])
            ->pluck('name')
            ->filter()
            ->take(2)
            ->implode(', ');

        $this->notificationCenterService->notifyUsers($users, [
            'event_key' => $eventKey,
            'module' => 'pharmacy',
            'level' => 'warning',
            'title' => 'Low stock reorder recommendation',
            'message' => trim(sprintf(
                '%s di branch %s tersisa %.2f %s. Rekomendasi reorder %.2f %s%s.',
                $policy->medicine?->name ?? 'Medicine',
                $policy->branch?->code ?? '-',
                (float) $recommendation['available_quantity'],
                $policy->medicine?->base_unit ?? 'unit',
                (float) $recommendation['recommended_purchase_quantity'],
                $recommendation['purchase_unit_label'] ?? ($policy->medicine?->base_unit ?? 'unit'),
                $supplierLabel !== '' ? ' via ' . $supplierLabel : ''
            )),
            'action_url' => route('reorder-points') . '?branch=' . $branchId . '&search=' . urlencode((string) ($policy->medicine?->code ?? $policy->medicine?->name ?? '')),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function supplierLabelsForPolicy(MedicineReorderPolicy $policy): array
    {
        return $policy->supplierPreferences
            ->filter(fn ($link) => $link->is_active)
            ->map(fn ($link) => trim(($link->supplier?->code ?? '-') . ' - ' . ($link->supplier?->name ?? '-')))
            ->values()
            ->all();
    }

    private function policyTable(array $filters): LengthAwarePaginator
    {
        $paginator = MedicineReorderPolicy::query()
            ->with($this->relations())
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->whereHas('medicine', function (Builder $medicineQuery) use ($filters): void {
                            $medicineQuery
                                ->where('code', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('name', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('generic_name', 'like', '%' . $filters['search'] . '%');
                        })
                        ->orWhereHas('branch', fn (Builder $branchQuery) => $branchQuery->where('name', 'like', '%' . $filters['search'] . '%'))
                        ->orWhereHas('supplierPreferences.supplier', fn (Builder $supplierQuery) => $supplierQuery->where('name', 'like', '%' . $filters['search'] . '%'));
                });
            })
            ->when($filters['branch'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
            ->orderByDesc('is_active')
            ->orderBy('branch_id')
            ->orderBy('medicine_id')
            ->paginate(10, ['*'], 'reorder_page')
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (MedicineReorderPolicy $policy) => tap($policy, function (MedicineReorderPolicy $row): void {
                $row->setAttribute('computed_reorder', $this->decoratePolicy($row));
            }))
        );

        return $paginator;
    }

    private function lowStockRecommendations(array $filters, int $limit = 8): Collection
    {
        return MedicineReorderPolicy::query()
            ->with($this->relations())
            ->where('is_active', true)
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->whereHas('medicine', function (Builder $medicineQuery) use ($filters): void {
                    $medicineQuery
                        ->where('code', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('name', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when($filters['branch'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->get()
            ->map(fn (MedicineReorderPolicy $policy): array => $this->decoratePolicy($policy))
            ->filter(fn (array $recommendation): bool => $recommendation['is_low_stock'])
            ->sortBy([
                ['stock_ratio', 'asc'],
                ['available_quantity', 'asc'],
            ])
            ->take($limit)
            ->values();
    }

    private function lowStockCount(array $filters): int
    {
        return MedicineReorderPolicy::query()
            ->with($this->relations())
            ->where('is_active', true)
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->whereHas('medicine', function (Builder $medicineQuery) use ($filters): void {
                    $medicineQuery
                        ->where('code', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('name', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when($filters['branch'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->get()
            ->map(fn (MedicineReorderPolicy $policy): array => $this->decoratePolicy($policy))
            ->filter(fn (array $recommendation): bool => $recommendation['is_low_stock'])
            ->count();
    }

    private function decoratePolicy(MedicineReorderPolicy $policy): array
    {
        $availableQuantity = $this->availableQuantity((int) $policy->medicine_id, (int) $policy->branch_id);
        $thresholdTarget = max(
            (float) $policy->minimum_stock,
            (float) $policy->reorder_point + (float) $policy->safety_stock
        );
        $recommendedOrderQuantity = max(
            (float) $policy->reorder_quantity,
            max(0, round($thresholdTarget - $availableQuantity, 2))
        );
        $purchaseFactor = (float) ($policy->preferredPurchaseUnit?->conversion_factor ?: 1);
        $reorderPoint = max((float) $policy->reorder_point, 0.01);

        return [
            'policy_id' => $policy->id,
            'medicine' => $policy->medicine,
            'branch' => $policy->branch,
            'suppliers' => $policy->supplierPreferences
                ->filter(fn ($link) => $link->is_active)
                ->map(fn ($link): array => [
                    'id' => $link->supplier_id,
                    'name' => $link->supplier?->name ?? '-',
                    'code' => $link->supplier?->code ?? '-',
                    'priority' => $link->priority,
                    'is_primary' => $link->is_primary,
                ])
                ->values()
                ->all(),
            'available_quantity' => round($availableQuantity, 2),
            'reorder_point' => (float) $policy->reorder_point,
            'minimum_stock' => (float) $policy->minimum_stock,
            'safety_stock' => (float) $policy->safety_stock,
            'recommended_order_quantity' => round($recommendedOrderQuantity, 2),
            'recommended_purchase_quantity' => round($recommendedOrderQuantity / max($purchaseFactor, 0.0001), 2),
            'preferred_purchase_unit_id' => $policy->preferred_purchase_unit_id,
            'purchase_unit_label' => $policy->preferredPurchaseUnit?->label ?? $policy->medicine?->base_unit,
            'stock_ratio' => round($availableQuantity / $reorderPoint, 4),
            'is_low_stock' => $availableQuantity <= (float) $policy->reorder_point,
        ];
    }

    private function availableQuantity(int $medicineId, int $branchId): float
    {
        return (float) MedicineBatch::query()
            ->where('medicine_id', $medicineId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('quantity_available', '>', 0)
            ->whereNull('quarantined_at')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('expired_at')
                    ->orWhereDate('expired_at', '>=', now()->toDateString());
            })
            ->sum('quantity_available');
    }

    private function syncSuppliers(MedicineReorderPolicy $policy, array $rows): void
    {
        $rows = collect($rows)
            ->map(function (array $row, int $index): array {
                return [
                    'supplier_id' => filled($row['supplier_id'] ?? null) ? (int) $row['supplier_id'] : null,
                    'priority' => filled($row['priority'] ?? null) ? (int) $row['priority'] : (($index + 1) * 10),
                    'is_primary' => (bool) ($row['is_primary'] ?? false),
                    'is_active' => (bool) ($row['is_active'] ?? true),
                ];
            })
            ->filter(fn (array $row): bool => $row['supplier_id'] !== null)
            ->values();

        $kept = [];

        foreach ($rows as $index => $row) {
            $link = $policy->supplierPreferences()->updateOrCreate(
                ['supplier_id' => $row['supplier_id']],
                [
                    'priority' => $row['priority'],
                    'is_primary' => $index === 0 ? true : $row['is_primary'],
                    'is_active' => $row['is_active'],
                ]
            );

            $kept[] = $link->id;
        }

        $policy->supplierPreferences()
            ->when($kept !== [], fn (Builder $query) => $query->whereNotIn('id', $kept))
            ->delete();

        if ($rows->isNotEmpty()) {
            $firstSupplierId = $rows->first()['supplier_id'];
            $policy->supplierPreferences()
                ->where('supplier_id', '!=', $firstSupplierId)
                ->update(['is_primary' => false]);
        }
    }

    private function policyAttributes(array $payload, Medicine $medicine): array
    {
        $preferredUnit = $this->resolvePreferredPurchaseUnit($medicine, $payload['preferred_purchase_unit_id'] ?? null);

        return [
            'medicine_id' => $payload['medicine_id'],
            'branch_id' => $payload['branch_id'],
            'preferred_purchase_unit_id' => $preferredUnit?->id,
            'minimum_stock' => $payload['minimum_stock'],
            'safety_stock' => $payload['safety_stock'],
            'reorder_point' => $payload['reorder_point'],
            'reorder_quantity' => $payload['reorder_quantity'],
            'lead_time_days' => $payload['lead_time_days'],
            'notes' => $payload['notes'] ?? null,
            'is_active' => $payload['is_active'],
        ];
    }

    private function relations(): array
    {
        return [
            'medicine:id,code,name,generic_name,base_unit',
            'branch:id,code,name',
            'preferredPurchaseUnit:id,medicine_id,label,conversion_factor',
            'supplierPreferences.supplier:id,code,name',
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'search' => trim((string) ($filters['search'] ?? '')),
            'branch' => filled($filters['branch'] ?? null) ? (string) $filters['branch'] : '',
            'status' => (string) ($filters['status'] ?? ''),
        ];
    }

    private function resolvePreferredPurchaseUnit(Medicine $medicine, ?int $unitId): ?MedicineUnit
    {
        $medicine->loadMissing(['units' => fn ($query) => $query->where('is_active', true)]);

        /** @var MedicineUnit|null $unit */
        $unit = $unitId
            ? $medicine->units->firstWhere('id', $unitId)
            : $medicine->units->first(fn (MedicineUnit $candidate): bool => $candidate->allow_purchase)
                ?? $medicine->units->first(fn (MedicineUnit $candidate): bool => $candidate->is_base);

        if (! $unit) {
            return null;
        }

        if (! $unit->allow_purchase) {
            throw ValidationException::withMessages([
                'preferred_purchase_unit_id' => 'UOM terpilih tidak diizinkan untuk purchase.',
            ]);
        }

        return $unit;
    }

    private function abilities(): array
    {
        return [
            'create' => auth()->check(),
            'edit' => auth()->check(),
            'delete' => auth()->check(),
        ];
    }
}
