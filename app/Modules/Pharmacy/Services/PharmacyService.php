<?php

namespace App\Modules\Pharmacy\Services;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineUnit;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Services\ReorderPointService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PharmacyService
{
    public function __construct(
        private readonly ReorderPointService $reorderPointService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'branch' => filled($filters['branch'] ?? null) ? (string) $filters['branch'] : '',
            'status' => (string) ($filters['status'] ?? ''),
            'batch_status' => (string) ($filters['batch_status'] ?? ''),
        ];

        return [
            'filters' => $filters,
            'medicines' => $this->medicineTable($filters),
            'batches' => $this->batchTable($filters),
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'name', 'code']),
            'categoryOptions' => ProductCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'supplierOptions' => Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'medicineOptions' => Medicine::query()
                ->with(['units' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('conversion_factor')])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'strength', 'base_unit']),
            'abilities' => $this->abilities(),
        ];
    }

    public function createMedicine(array $payload): Medicine
    {
        return DB::transaction(function () use ($payload): Medicine {
            $medicine = Medicine::query()->create($this->medicineAttributes($payload));
            $this->syncUnits($medicine, $payload['uoms'] ?? []);
            $this->syncBranchPrices($medicine, $payload['branch_prices'] ?? []);

            return $medicine->fresh(['units', 'branchPrices']);
        });
    }

    public function updateMedicine(Medicine $medicine, array $payload): Medicine
    {
        return DB::transaction(function () use ($medicine, $payload): Medicine {
            $medicine->update($this->medicineAttributes($payload));
            $this->syncUnits($medicine, $payload['uoms'] ?? []);
            $this->syncBranchPrices($medicine, $payload['branch_prices'] ?? []);

            return $medicine->fresh(['units', 'branchPrices']);
        });
    }

    public function deleteMedicine(Medicine $medicine): void
    {
        $medicine->update([
            'is_active' => false,
        ]);
    }

    public function createBatch(array $payload): MedicineBatch
    {
        $batch = MedicineBatch::query()->create($this->batchAttributes($payload));
        $this->reorderPointService->refreshForMedicineBranch((int) $batch->medicine_id, (int) $batch->branch_id);

        return $batch;
    }

    public function updateBatch(MedicineBatch $batch, array $payload): MedicineBatch
    {
        if ($batch->dispenseBatches()->exists() && (float) $payload['quantity_available'] > (float) $batch->quantity_available) {
            throw ValidationException::withMessages([
                'quantity_available' => 'Batch yang sudah dipakai dispense tidak bisa dinaikkan sembarangan. Buat batch baru bila perlu tambah stok.',
            ]);
        }

        $batch->update($this->batchAttributes($payload));
        $this->reorderPointService->refreshForMedicineBranch((int) $batch->medicine_id, (int) $batch->branch_id);

        return $batch->fresh(['medicine', 'branch']);
    }

    public function deleteBatch(MedicineBatch $batch): void
    {
        $batch->update([
            'is_active' => false,
        ]);
        $this->reorderPointService->refreshForMedicineBranch((int) $batch->medicine_id, (int) $batch->branch_id);
    }

    private function medicineTable(array $filters): LengthAwarePaginator
    {
        return Medicine::query()
            ->with([
                'productCategory:id,code,name',
                'units' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('conversion_factor')
                    ->select('id', 'medicine_id', 'label', 'conversion_factor', 'is_base', 'allow_purchase', 'allow_dispense', 'sort_order', 'is_active'),
                'branchPrices.branch:id,name,code',
                'batches' => fn ($query) => $query
                    ->select('id', 'medicine_id', 'branch_id', 'batch_number', 'expired_at', 'quantity_available', 'is_active')
                    ->with('branch:id,name,code')
                    ->orderBy('expired_at')
                    ->orderBy('received_at'),
            ])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('code', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('generic_name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('dosage_form', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('strength', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
            ->orderBy('name')
            ->paginate(10, ['*'], 'medicine_page')
            ->withQueryString();
    }

    private function batchTable(array $filters): LengthAwarePaginator
    {
        return MedicineBatch::query()
            ->with([
                'medicine:id,code,name,strength,base_unit',
                'branch:id,name,code',
                'supplier:id,code,name',
            ])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('batch_number', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('medicine', function (Builder $medicineQuery) use ($filters): void {
                            $medicineQuery
                                ->where('code', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('name', 'like', '%' . $filters['search'] . '%');
                        });
                });
            })
            ->when($filters['branch'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
            ->when($filters['batch_status'] !== '', function (Builder $query) use ($filters): void {
                match ($filters['batch_status']) {
                    'expired' => $query->whereNotNull('expired_at')->whereDate('expired_at', '<', now()->toDateString()),
                    'empty' => $query->where('quantity_available', '<=', 0),
                    'available' => $query->where('quantity_available', '>', 0)->where(function (Builder $availabilityQuery): void {
                        $availabilityQuery
                            ->whereNull('expired_at')
                            ->orWhereDate('expired_at', '>=', now()->toDateString());
                    }),
                    default => null,
                };
            })
            ->orderByRaw('CASE WHEN expired_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expired_at')
            ->orderByDesc('received_at')
            ->paginate(10, ['*'], 'batch_page')
            ->withQueryString();
    }

    private function medicineAttributes(array $payload): array
    {
        $baseUnit = $this->baseUnitLabel($payload['uoms'] ?? [], $payload['base_unit'] ?? null);

        return [
            'code' => strtoupper(trim($payload['code'])),
            'product_category_id' => $payload['product_category_id'] ?? null,
            'name' => trim($payload['name']),
            'generic_name' => $payload['generic_name'],
            'active_ingredients' => $payload['active_ingredients'],
            'dosage_form' => trim($payload['dosage_form']),
            'therapeutic_class' => $payload['therapeutic_class'],
            'strength' => $payload['strength'],
            'base_unit' => $baseUnit,
            'description' => $payload['description'],
            'contraindication_notes' => $payload['contraindication_notes'],
            'allergy_keywords' => $payload['allergy_keywords'],
            'is_compoundable' => $payload['is_compoundable'],
            'is_active' => $payload['is_active'],
        ];
    }

    private function batchAttributes(array $payload): array
    {
        if ((float) $payload['quantity_available'] > (float) $payload['quantity_received']) {
            throw ValidationException::withMessages([
                'quantity_available' => 'Quantity available tidak boleh lebih besar dari quantity received.',
            ]);
        }

        $supplier = filled($payload['supplier_id'] ?? null)
            ? Supplier::query()->find($payload['supplier_id'])
            : null;

        return [
            'medicine_id' => $payload['medicine_id'],
            'branch_id' => $payload['branch_id'],
            'supplier_id' => $supplier?->id,
            'batch_number' => strtoupper(trim($payload['batch_number'])),
            'received_at' => $payload['received_at'],
            'expired_at' => $payload['expired_at'],
            'quantity_received' => $payload['quantity_received'],
            'quantity_available' => $payload['quantity_available'],
            'purchase_cost' => $payload['purchase_cost'],
            'supplier_name' => $supplier?->name ?? $payload['supplier_name'],
            'notes' => $payload['notes'],
            'is_active' => $payload['is_active'],
        ];
    }

    private function syncBranchPrices(Medicine $medicine, array $branchPrices): void
    {
        foreach (Branch::query()->pluck('id') as $branchId) {
            $price = $branchPrices[$branchId] ?? null;

            if ($price === null || $price === '') {
                $medicine->branchPrices()->where('branch_id', $branchId)->delete();
                continue;
            }

            $medicine->branchPrices()->updateOrCreate(
                ['branch_id' => $branchId],
                [
                    'selling_price' => $price,
                    'is_active' => true,
                ],
            );
        }
    }

    private function syncUnits(Medicine $medicine, array $rows): void
    {
        $rows = collect($rows)
            ->map(fn (array $row) => [
                'id' => $row['id'] ?? null,
                'label' => strtoupper(trim((string) ($row['label'] ?? ''))),
                'conversion_factor' => (float) ($row['conversion_factor'] ?? 0),
                'allow_purchase' => (bool) ($row['allow_purchase'] ?? false),
                'allow_dispense' => (bool) ($row['allow_dispense'] ?? false),
                'is_base' => (bool) ($row['is_base'] ?? false),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['label'] !== '')
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'uoms' => 'Minimal satu level UOM harus diisi.',
            ]);
        }

        $baseRows = $rows->filter(fn (array $row): bool => $row['is_base']);

        if ($baseRows->count() !== 1) {
            throw ValidationException::withMessages([
                'uoms' => 'Harus ada tepat satu base unit.',
            ]);
        }

        if ((float) $baseRows->first()['conversion_factor'] !== 1.0) {
            throw ValidationException::withMessages([
                'uoms' => 'Base unit harus memiliki conversion factor 1.',
            ]);
        }

        $existing = $medicine->units()->get();
        $existingById = $existing->keyBy('id');
        $existingByLabel = $existing->keyBy(fn (MedicineUnit $unit): string => strtoupper($unit->label));
        $keptIds = [];

        foreach ($rows as $row) {
            /** @var MedicineUnit|null $unit */
            $unit = $row['id']
                ? $existingById->get($row['id'])
                : $existingByLabel->get($row['label']);

            if (! $unit) {
                $unit = new MedicineUnit();
                $unit->medicine()->associate($medicine);
            }

            $unit->fill([
                'label' => $row['label'],
                'conversion_factor' => $row['conversion_factor'],
                'allow_purchase' => $row['allow_purchase'],
                'allow_dispense' => $row['allow_dispense'],
                'is_base' => $row['is_base'],
                'sort_order' => $row['sort_order'],
                'is_active' => true,
            ]);
            $unit->save();
            $keptIds[] = $unit->id;
        }

        $medicine->units()
            ->when($keptIds !== [], fn (Builder $query) => $query->whereNotIn('id', $keptIds))
            ->delete();
    }

    private function baseUnitLabel(array $rows, ?string $fallback): string
    {
        $base = collect($rows)->first(fn (array $row): bool => (bool) ($row['is_base'] ?? false));

        if ($base) {
            return strtoupper(trim((string) $base['label']));
        }

        return strtoupper(trim((string) ($fallback ?: 'UNIT')));
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
