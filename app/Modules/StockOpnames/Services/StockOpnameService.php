<?php

namespace App\Modules\StockOpnames\Services;

use App\Models\Branch;
use App\Models\MedicineBatch;
use App\Models\StockOpname;
use App\Services\AuditLogService;
use App\Services\InventoryBatchService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockOpnameService
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
            'status' => (string) ($filters['status'] ?? ''),
            'date' => (string) ($filters['date'] ?? ''),
        ];

        return [
            'filters' => $filters,
            'stockOpnames' => $this->table($filters),
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'code', 'name']),
            'batchOptions' => $this->batchOptions(),
            'abilities' => $this->abilities(),
        ];
    }

    public function create(array $payload): StockOpname
    {
        return DB::transaction(function () use ($payload): StockOpname {
            $stockOpname = StockOpname::query()->create([
                'branch_id' => $payload['branch_id'],
                'created_by_user_id' => auth()->id(),
                'opname_no' => $this->nextOpnameNo(),
                'status' => 'draft',
                'opname_date' => $payload['opname_date'],
                'notes' => $payload['notes'] ?? null,
            ]);

            $this->syncItems($stockOpname, $payload['items'] ?? []);

            $fresh = $stockOpname->fresh($this->relations());

            $this->auditLogService->log(
                'stock_opnames',
                'created',
                $fresh,
                'Stock opname baru dibuat.',
                [],
                $fresh?->toArray() ?? [],
            );

            return $fresh;
        });
    }

    public function update(StockOpname $stockOpname, array $payload): StockOpname
    {
        $this->ensureDraft($stockOpname, 'Stock opname yang sudah diproses tidak bisa diubah.');

        return DB::transaction(function () use ($stockOpname, $payload): StockOpname {
            $before = $stockOpname->fresh($this->relations())?->toArray() ?? [];

            $stockOpname->update([
                'branch_id' => $payload['branch_id'],
                'opname_date' => $payload['opname_date'],
                'notes' => $payload['notes'] ?? null,
            ]);

            $this->syncItems($stockOpname, $payload['items'] ?? []);

            $fresh = $stockOpname->fresh($this->relations());

            $this->auditLogService->log(
                'stock_opnames',
                'updated',
                $fresh,
                'Stock opname diperbarui.',
                $before,
                $fresh?->toArray() ?? [],
            );

            return $fresh;
        });
    }

    public function finalize(StockOpname $stockOpname): StockOpname
    {
        $this->ensureDraft($stockOpname, 'Hanya stock opname draft yang bisa difinalkan.');

        return DB::transaction(function () use ($stockOpname): StockOpname {
            $stockOpname->loadMissing('items.medicineBatch');

            if ($stockOpname->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'stock_opname' => 'Stock opname harus memiliki minimal satu item.',
                ]);
            }

            $before = $stockOpname->fresh($this->relations())?->toArray() ?? [];

            foreach ($stockOpname->items as $item) {
                $batch = $item->medicineBatch;

                if (! $batch) {
                    throw ValidationException::withMessages([
                        'stock_opname' => 'Ada item opname yang kehilangan referensi batch.',
                    ]);
                }

                $systemQuantity = (float) $batch->quantity_available;
                $countedQuantity = (float) $item->counted_quantity;

                $updatedBatch = $this->inventoryBatchService->setAvailable($batch, $countedQuantity, 'stock_opname');

                $item->update([
                    'system_quantity_snapshot' => $systemQuantity,
                    'variance_quantity' => round($countedQuantity - $systemQuantity, 2),
                ]);

                if (! $updatedBatch->is_active && $countedQuantity > 0 && ! $updatedBatch->isExpired()) {
                    $updatedBatch->update([
                        'is_active' => true,
                        'quarantined_at' => null,
                        'quarantined_by_user_id' => null,
                        'quarantine_reason' => null,
                    ]);
                }
            }

            $stockOpname->update([
                'status' => 'finalized',
                'finalized_at' => now(),
                'finalized_by_user_id' => auth()->id(),
            ]);

            $fresh = $stockOpname->fresh($this->relations());

            $this->auditLogService->log(
                'stock_opnames',
                'finalized',
                $fresh,
                'Stock opname difinalkan dan quantity batch disesuaikan.',
                $before,
                $fresh?->toArray() ?? [],
            );

            return $fresh;
        });
    }

    public function cancel(StockOpname $stockOpname, array $payload): StockOpname
    {
        $this->ensureDraft($stockOpname, 'Hanya stock opname draft yang bisa dibatalkan.');

        $before = $stockOpname->fresh($this->relations())?->toArray() ?? [];

        $stockOpname->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by_user_id' => auth()->id(),
            'cancel_reason' => $payload['cancel_reason'],
            'notes' => $payload['notes'] ?? $stockOpname->notes,
        ]);

        $fresh = $stockOpname->fresh($this->relations());

        $this->auditLogService->log(
            'stock_opnames',
            'cancelled',
            $fresh,
            'Stock opname dibatalkan.',
            $before,
            $fresh?->toArray() ?? [],
            [
                'cancel_reason' => $payload['cancel_reason'],
            ],
        );

        return $fresh;
    }

    public function delete(StockOpname $stockOpname): void
    {
        $this->ensureDraft($stockOpname, 'Hanya stock opname draft yang bisa dihapus.');

        $before = $stockOpname->fresh($this->relations())?->toArray() ?? [];

        $stockOpname->delete();

        $this->auditLogService->log(
            'stock_opnames',
            'archived',
            $stockOpname,
            'Stock opname draft diarsipkan.',
            $before,
            $stockOpname->fresh($this->relations())?->toArray() ?? [],
            [
                'stock_opname_id' => $stockOpname->id,
                'opname_no' => $before['opname_no'] ?? null,
            ],
        );
    }

    private function table(array $filters): LengthAwarePaginator
    {
        return StockOpname::query()
            ->with($this->relations())
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('opname_no', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('items.medicine', function (Builder $medicineQuery) use ($filters): void {
                            $medicineQuery
                                ->where('code', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('name', 'like', '%' . $filters['search'] . '%');
                        })
                        ->orWhereHas('items.medicineBatch', fn (Builder $batchQuery) => $batchQuery->where('batch_number', 'like', '%' . $filters['search'] . '%'));
                });
            })
            ->when($filters['branch'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['date'] !== '', fn (Builder $query) => $query->whereDate('opname_date', $filters['date']))
            ->orderByDesc('opname_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function syncItems(StockOpname $stockOpname, array $items): void
    {
        $items = collect($items)
            ->map(fn (array $item): array => [
                'id' => filled($item['id'] ?? null) ? (int) $item['id'] : null,
                'medicine_batch_id' => (int) $item['medicine_batch_id'],
                'counted_quantity' => (float) $item['counted_quantity'],
                'notes' => $item['notes'] ?? null,
            ])
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Stock opname harus memiliki minimal satu item.',
            ]);
        }

        $existingItems = $stockOpname->items()->get()->keyBy('id');
        $keptIds = [];

        foreach ($items as $itemPayload) {
            $batch = MedicineBatch::query()->findOrFail($itemPayload['medicine_batch_id']);

            if ($batch->branch_id !== $stockOpname->branch_id) {
                throw ValidationException::withMessages([
                    'items' => 'Batch opname harus berasal dari branch yang sama.',
                ]);
            }

            $attributes = [
                'medicine_batch_id' => $batch->id,
                'medicine_id' => $batch->medicine_id,
                'system_quantity_snapshot' => (float) $batch->quantity_available,
                'counted_quantity' => $itemPayload['counted_quantity'],
                'variance_quantity' => round($itemPayload['counted_quantity'] - (float) $batch->quantity_available, 2),
                'notes' => $itemPayload['notes'],
            ];

            if ($itemPayload['id'] && $existingItems->has($itemPayload['id'])) {
                $record = $existingItems->get($itemPayload['id']);
                $record->update($attributes);
                $keptIds[] = $record->id;
                continue;
            }

            $record = $stockOpname->items()->create($attributes);
            $keptIds[] = $record->id;
        }

        $stockOpname->items()
            ->when($keptIds !== [], fn (Builder $query) => $query->whereNotIn('id', $keptIds))
            ->when($keptIds === [], fn (Builder $query) => $query)
            ->delete();
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
                    'system_quantity' => (string) $batch->quantity_available,
                    'supplier_label' => trim(($batch->supplier?->code ?? '-') . ' - ' . ($batch->supplier?->name ?? '-')),
                    'expired_at' => $batch->expired_at?->format('Y-m-d'),
                    'is_active' => $batch->is_active,
                ];
            })
            ->values();
    }

    private function ensureDraft(StockOpname $stockOpname, string $message): void
    {
        if ($stockOpname->status !== 'draft') {
            throw ValidationException::withMessages([
                'stock_opname' => $message,
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
            'finalizedBy:id,name',
            'cancelledBy:id,name',
            'items.medicine:id,code,name,strength,base_unit',
            'items.medicineBatch:id,branch_id,medicine_id,batch_number,quantity_available,purchase_cost,expired_at,is_active',
        ];
    }

    private function abilities(): array
    {
        return [
            'create' => auth()->check(),
            'edit' => auth()->check(),
            'finalize' => auth()->check(),
            'cancel' => auth()->check(),
            'delete' => auth()->check(),
        ];
    }

    private function nextOpnameNo(): string
    {
        $today = now()->format('Ymd');

        $lastOpname = StockOpname::query()
            ->where('opname_no', 'like', 'OPN-' . $today . '-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $lastNumber = 0;

        if ($lastOpname && preg_match('/(\d+)$/', $lastOpname->opname_no, $matches) === 1) {
            $lastNumber = (int) $matches[1];
        }

        return sprintf('OPN-%s-%04d', $today, $lastNumber + 1);
    }
}
