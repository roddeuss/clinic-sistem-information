<?php

namespace App\Modules\StockAdjustments\Services;

use App\Models\Branch;
use App\Models\MedicineBatch;
use App\Models\StockAdjustment;
use App\Services\AuditLogService;
use App\Services\InventoryBatchService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly InventoryBatchService $inventoryBatchService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'branch' => filled($filters['branch'] ?? null) ? (string) $filters['branch'] : '',
            'type' => (string) ($filters['type'] ?? ''),
            'status' => (string) ($filters['status'] ?? ''),
            'date' => (string) ($filters['date'] ?? ''),
        ];

        return [
            'filters' => $filters,
            'stockAdjustments' => $this->table($filters),
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'code', 'name']),
            'batchOptions' => $this->batchOptions(),
            'abilities' => $this->abilities(),
        ];
    }

    public function create(array $payload): StockAdjustment
    {
        return DB::transaction(function () use ($payload): StockAdjustment {
            $stockAdjustment = StockAdjustment::query()->create([
                'branch_id' => $payload['branch_id'],
                'created_by_user_id' => auth()->id(),
                'adjustment_no' => $this->nextAdjustmentNo(),
                'adjustment_type' => $payload['adjustment_type'],
                'status' => 'draft',
                'adjustment_date' => $payload['adjustment_date'],
                'notes' => $payload['notes'] ?? null,
            ]);

            $this->syncItems($stockAdjustment, $payload['items'] ?? []);

            $fresh = $stockAdjustment->fresh($this->relations());

            $this->auditLogService->log(
                'stock_adjustments',
                'created',
                $fresh,
                'Stock adjustment baru dibuat.',
                [],
                $fresh?->toArray() ?? [],
            );

            return $fresh;
        });
    }

    public function update(StockAdjustment $stockAdjustment, array $payload): StockAdjustment
    {
        $this->ensureDraft($stockAdjustment, 'Stock adjustment yang sudah diproses tidak bisa diubah.');

        return DB::transaction(function () use ($stockAdjustment, $payload): StockAdjustment {
            $before = $stockAdjustment->fresh($this->relations())?->toArray() ?? [];

            $stockAdjustment->update([
                'branch_id' => $payload['branch_id'],
                'adjustment_type' => $payload['adjustment_type'],
                'adjustment_date' => $payload['adjustment_date'],
                'notes' => $payload['notes'] ?? null,
            ]);

            $this->syncItems($stockAdjustment, $payload['items'] ?? []);

            $fresh = $stockAdjustment->fresh($this->relations());

            $this->auditLogService->log(
                'stock_adjustments',
                'updated',
                $fresh,
                'Stock adjustment diperbarui.',
                $before,
                $fresh?->toArray() ?? [],
            );

            return $fresh;
        });
    }

    public function apply(StockAdjustment $stockAdjustment): StockAdjustment
    {
        $this->ensureDraft($stockAdjustment, 'Hanya stock adjustment draft yang bisa diterapkan.');

        return DB::transaction(function () use ($stockAdjustment): StockAdjustment {
            $stockAdjustment->loadMissing('items.medicineBatch');

            if ($stockAdjustment->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'stock_adjustment' => 'Stock adjustment harus memiliki minimal satu item.',
                ]);
            }

            $before = $stockAdjustment->fresh($this->relations())?->toArray() ?? [];

            foreach ($stockAdjustment->items as $item) {
                $batch = $item->medicineBatch;

                if (! $batch) {
                    throw ValidationException::withMessages([
                        'stock_adjustment' => 'Ada item adjustment yang kehilangan referensi batch.',
                    ]);
                }

                $quantityBefore = (float) $batch->quantity_available;
                $delta = (float) $item->quantity_delta;

                $updatedBatch = $delta < 0
                    ? $this->inventoryBatchService->decreaseAvailable($batch, abs($delta), 'stock_adjustment')
                    : $this->inventoryBatchService->increaseAvailable($batch, $delta, 'stock_adjustment');

                $item->update([
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => (float) $updatedBatch->quantity_available,
                    'unit_cost_snapshot' => $updatedBatch->purchase_cost,
                ]);
            }

            $stockAdjustment->update([
                'status' => 'applied',
                'applied_at' => now(),
                'applied_by_user_id' => auth()->id(),
            ]);

            $fresh = $stockAdjustment->fresh($this->relations());

            $this->auditLogService->log(
                'stock_adjustments',
                'applied',
                $fresh,
                'Stock adjustment diterapkan ke stok batch.',
                $before,
                $fresh?->toArray() ?? [],
            );

            return $fresh;
        });
    }

    public function cancel(StockAdjustment $stockAdjustment, array $payload): StockAdjustment
    {
        $this->ensureDraft($stockAdjustment, 'Hanya stock adjustment draft yang bisa dibatalkan.');

        $before = $stockAdjustment->fresh($this->relations())?->toArray() ?? [];

        $stockAdjustment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by_user_id' => auth()->id(),
            'cancel_reason' => $payload['cancel_reason'],
            'notes' => $payload['notes'] ?? $stockAdjustment->notes,
        ]);

        $fresh = $stockAdjustment->fresh($this->relations());

        $this->auditLogService->log(
            'stock_adjustments',
            'cancelled',
            $fresh,
            'Stock adjustment dibatalkan.',
            $before,
            $fresh?->toArray() ?? [],
            [
                'cancel_reason' => $payload['cancel_reason'],
            ],
        );

        return $fresh;
    }

    public function delete(StockAdjustment $stockAdjustment): void
    {
        $this->ensureDraft($stockAdjustment, 'Hanya stock adjustment draft yang bisa dihapus.');

        $before = $stockAdjustment->fresh($this->relations())?->toArray() ?? [];

        $stockAdjustment->delete();

        $this->auditLogService->log(
            'stock_adjustments',
            'archived',
            $stockAdjustment,
            'Stock adjustment draft diarsipkan.',
            $before,
            $stockAdjustment->fresh($this->relations())?->toArray() ?? [],
            [
                'stock_adjustment_id' => $stockAdjustment->id,
                'adjustment_no' => $before['adjustment_no'] ?? null,
            ],
        );
    }

    private function table(array $filters): LengthAwarePaginator
    {
        return StockAdjustment::query()
            ->with($this->relations())
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('adjustment_no', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('items.medicine', function (Builder $medicineQuery) use ($filters): void {
                            $medicineQuery
                                ->where('code', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('name', 'like', '%' . $filters['search'] . '%');
                        })
                        ->orWhereHas('items.medicineBatch', fn (Builder $batchQuery) => $batchQuery->where('batch_number', 'like', '%' . $filters['search'] . '%'));
                });
            })
            ->when($filters['branch'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->when($filters['type'] !== '', fn (Builder $query) => $query->where('adjustment_type', $filters['type']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['date'] !== '', fn (Builder $query) => $query->whereDate('adjustment_date', $filters['date']))
            ->orderByDesc('adjustment_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function syncItems(StockAdjustment $stockAdjustment, array $items): void
    {
        $items = collect($items)
            ->map(fn (array $item): array => [
                'id' => filled($item['id'] ?? null) ? (int) $item['id'] : null,
                'medicine_batch_id' => (int) $item['medicine_batch_id'],
                'quantity_adjusted' => (float) $item['quantity_adjusted'],
                'reason' => $item['reason'] ?? null,
                'notes' => $item['notes'] ?? null,
            ])
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Stock adjustment harus memiliki minimal satu item.',
            ]);
        }

        $existingItems = $stockAdjustment->items()->get()->keyBy('id');
        $keptIds = [];

        foreach ($items as $itemPayload) {
            $batch = MedicineBatch::query()->findOrFail($itemPayload['medicine_batch_id']);

            if ($batch->branch_id !== $stockAdjustment->branch_id) {
                throw ValidationException::withMessages([
                    'items' => 'Batch adjustment harus berasal dari branch yang sama.',
                ]);
            }

            $signedDelta = $stockAdjustment->adjustment_type === 'decrease'
                ? -1 * abs($itemPayload['quantity_adjusted'])
                : abs($itemPayload['quantity_adjusted']);

            $attributes = [
                'medicine_batch_id' => $batch->id,
                'medicine_id' => $batch->medicine_id,
                'quantity_before' => 0,
                'quantity_delta' => $signedDelta,
                'quantity_after' => 0,
                'unit_cost_snapshot' => $batch->purchase_cost,
                'reason' => $itemPayload['reason'],
                'notes' => $itemPayload['notes'],
            ];

            if ($itemPayload['id'] && $existingItems->has($itemPayload['id'])) {
                $record = $existingItems->get($itemPayload['id']);
                $record->update($attributes);
                $keptIds[] = $record->id;
                continue;
            }

            $record = $stockAdjustment->items()->create($attributes);
            $keptIds[] = $record->id;
        }

        $stockAdjustment->items()
            ->when($keptIds !== [], fn (Builder $query) => $query->whereNotIn('id', $keptIds))
            ->when($keptIds === [], fn (Builder $query) => $query)
            ->delete();

        $stockAdjustment->update([
            'total_items' => (int) $stockAdjustment->items()->count(),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function batchOptions(): Collection
    {
        return MedicineBatch::query()
            ->with([
                'branch:id,code,name',
                'medicine:id,code,name,strength,base_unit',
                'supplier:id,code,name',
            ])
            ->orderBy('branch_id')
            ->orderBy('expired_at')
            ->orderBy('received_at')
            ->get()
            ->map(function (MedicineBatch $batch): array {
                return [
                    'id' => (string) $batch->id,
                    'branch_id' => (string) $batch->branch_id,
                    'label' => trim(($batch->medicine?->code ?? '-') . ' - ' . ($batch->medicine?->name ?? '-') . ' | Batch ' . $batch->batch_number),
                    'branch_label' => trim(($batch->branch?->code ?? '-') . ' - ' . ($batch->branch?->name ?? '-')),
                    'available_quantity' => (string) $batch->quantity_available,
                    'purchase_cost' => (string) ($batch->purchase_cost ?? 0),
                    'supplier_label' => trim(($batch->supplier?->code ?? '-') . ' - ' . ($batch->supplier?->name ?? '-')),
                    'expired_at' => $batch->expired_at?->format('Y-m-d'),
                    'is_active' => $batch->is_active,
                ];
            })
            ->values();
    }

    private function ensureDraft(StockAdjustment $stockAdjustment, string $message): void
    {
        if ($stockAdjustment->status !== 'draft') {
            throw ValidationException::withMessages([
                'stock_adjustment' => $message,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'branch:id,code,name',
            'createdBy:id,name',
            'appliedBy:id,name',
            'cancelledBy:id,name',
            'items.medicine:id,code,name,strength,base_unit',
            'items.medicineBatch:id,branch_id,medicine_id,batch_number,quantity_available,purchase_cost,expired_at',
        ];
    }

    private function abilities(): array
    {
        return [
            'create' => auth()->check(),
            'edit' => auth()->check(),
            'apply' => auth()->check(),
            'cancel' => auth()->check(),
            'delete' => auth()->check(),
        ];
    }

    private function nextAdjustmentNo(): string
    {
        $today = now()->format('Ymd');

        $lastAdjustment = StockAdjustment::query()
            ->where('adjustment_no', 'like', 'ADJ-' . $today . '-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $lastNumber = 0;

        if ($lastAdjustment && preg_match('/(\d+)$/', $lastAdjustment->adjustment_no, $matches) === 1) {
            $lastNumber = (int) $matches[1];
        }

        return sprintf('ADJ-%s-%04d', $today, $lastNumber + 1);
    }
}
