<?php

namespace App\Modules\PurchaseReturns\Services;

use App\Models\Branch;
use App\Models\MedicineBatch;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Services\InventoryBatchService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnService
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
            'supplier' => filled($filters['supplier'] ?? null) ? (string) $filters['supplier'] : '',
            'status' => (string) ($filters['status'] ?? ''),
            'date' => (string) ($filters['date'] ?? ''),
        ];

        return [
            'filters' => $filters,
            'purchaseReturns' => $this->table($filters),
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'code', 'name']),
            'supplierOptions' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'batchOptions' => $this->batchOptions(),
            'abilities' => $this->abilities(),
        ];
    }

    public function create(array $payload): PurchaseReturn
    {
        return DB::transaction(function () use ($payload): PurchaseReturn {
            $purchaseReturn = PurchaseReturn::query()->create([
                'branch_id' => $payload['branch_id'],
                'supplier_id' => $payload['supplier_id'],
                'created_by_user_id' => auth()->id(),
                'return_no' => $this->nextReturnNo(),
                'status' => 'draft',
                'return_date' => $payload['return_date'],
                'notes' => $payload['notes'] ?? null,
            ]);

            $this->syncItems($purchaseReturn, $payload['items'] ?? []);
            $this->refreshTotals($purchaseReturn);

            $fresh = $purchaseReturn->fresh($this->relations());

            $this->auditLogService->log(
                'purchase_returns',
                'created',
                $fresh,
                'Purchase return baru dibuat.',
                [],
                $fresh?->toArray() ?? [],
            );

            return $fresh;
        });
    }

    public function update(PurchaseReturn $purchaseReturn, array $payload): PurchaseReturn
    {
        $this->ensureDraft($purchaseReturn, 'Purchase return yang sudah diproses tidak bisa diubah.');

        return DB::transaction(function () use ($purchaseReturn, $payload): PurchaseReturn {
            $before = $purchaseReturn->fresh($this->relations())?->toArray() ?? [];

            $purchaseReturn->update([
                'branch_id' => $payload['branch_id'],
                'supplier_id' => $payload['supplier_id'],
                'return_date' => $payload['return_date'],
                'notes' => $payload['notes'] ?? null,
            ]);

            $this->syncItems($purchaseReturn, $payload['items'] ?? []);
            $this->refreshTotals($purchaseReturn);

            $fresh = $purchaseReturn->fresh($this->relations());

            $this->auditLogService->log(
                'purchase_returns',
                'updated',
                $fresh,
                'Purchase return diperbarui.',
                $before,
                $fresh?->toArray() ?? [],
            );

            return $fresh;
        });
    }

    public function complete(PurchaseReturn $purchaseReturn): PurchaseReturn
    {
        $this->ensureDraft($purchaseReturn, 'Hanya purchase return draft yang bisa diselesaikan.');

        return DB::transaction(function () use ($purchaseReturn): PurchaseReturn {
            $purchaseReturn->loadMissing('items.medicineBatch');

            if ($purchaseReturn->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'purchase_return' => 'Purchase return harus memiliki minimal satu item.',
                ]);
            }

            $before = $purchaseReturn->fresh($this->relations())?->toArray() ?? [];

            foreach ($purchaseReturn->items as $item) {
                $batch = $item->medicineBatch;

                if (! $batch) {
                    throw ValidationException::withMessages([
                        'purchase_return' => 'Ada item return yang kehilangan referensi batch.',
                    ]);
                }

                $this->inventoryBatchService->decreaseAvailable($batch, (float) $item->quantity_returned, 'purchase_return');
            }

            $purchaseReturn->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by_user_id' => auth()->id(),
            ]);

            $fresh = $purchaseReturn->fresh($this->relations());

            $this->auditLogService->log(
                'purchase_returns',
                'completed',
                $fresh,
                'Purchase return diselesaikan dan stok batch dikurangi.',
                $before,
                $fresh?->toArray() ?? [],
            );

            return $fresh;
        });
    }

    public function cancel(PurchaseReturn $purchaseReturn, array $payload): PurchaseReturn
    {
        $this->ensureDraft($purchaseReturn, 'Hanya purchase return draft yang bisa dibatalkan.');

        $before = $purchaseReturn->fresh($this->relations())?->toArray() ?? [];

        $purchaseReturn->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by_user_id' => auth()->id(),
            'cancel_reason' => $payload['cancel_reason'],
            'notes' => $payload['notes'] ?? $purchaseReturn->notes,
        ]);

        $fresh = $purchaseReturn->fresh($this->relations());

        $this->auditLogService->log(
            'purchase_returns',
            'cancelled',
            $fresh,
            'Purchase return dibatalkan.',
            $before,
            $fresh?->toArray() ?? [],
            [
                'cancel_reason' => $payload['cancel_reason'],
            ],
        );

        return $fresh;
    }

    public function delete(PurchaseReturn $purchaseReturn): void
    {
        $this->ensureDraft($purchaseReturn, 'Hanya purchase return draft yang bisa dihapus.');

        $before = $purchaseReturn->fresh($this->relations())?->toArray() ?? [];

        $purchaseReturn->delete();

        $this->auditLogService->log(
            'purchase_returns',
            'archived',
            $purchaseReturn,
            'Purchase return draft diarsipkan.',
            $before,
            $purchaseReturn->fresh($this->relations())?->toArray() ?? [],
            [
                'purchase_return_id' => $purchaseReturn->id,
                'return_no' => $before['return_no'] ?? null,
            ],
        );
    }

    private function table(array $filters): LengthAwarePaginator
    {
        return PurchaseReturn::query()
            ->with($this->relations())
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('return_no', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('supplier', fn (Builder $supplierQuery) => $supplierQuery->where('name', 'like', '%' . $filters['search'] . '%'))
                        ->orWhereHas('items.medicine', function (Builder $medicineQuery) use ($filters): void {
                            $medicineQuery
                                ->where('code', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('name', 'like', '%' . $filters['search'] . '%');
                        })
                        ->orWhereHas('items.medicineBatch', fn (Builder $batchQuery) => $batchQuery->where('batch_number', 'like', '%' . $filters['search'] . '%'));
                });
            })
            ->when($filters['branch'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->when($filters['supplier'] !== '', fn (Builder $query) => $query->where('supplier_id', $filters['supplier']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['date'] !== '', fn (Builder $query) => $query->whereDate('return_date', $filters['date']))
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function syncItems(PurchaseReturn $purchaseReturn, array $items): void
    {
        $items = collect($items)
            ->map(fn (array $item): array => [
                'id' => filled($item['id'] ?? null) ? (int) $item['id'] : null,
                'medicine_batch_id' => (int) $item['medicine_batch_id'],
                'quantity_returned' => (float) $item['quantity_returned'],
                'reason' => $item['reason'] ?? null,
                'notes' => $item['notes'] ?? null,
            ])
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Purchase return harus memiliki minimal satu item.',
            ]);
        }

        $existingItems = $purchaseReturn->items()->get()->keyBy('id');
        $keptIds = [];

        foreach ($items as $itemPayload) {
            $batch = MedicineBatch::query()
                ->with(['goodsReceiptItems'])
                ->findOrFail($itemPayload['medicine_batch_id']);

            if ($batch->branch_id !== $purchaseReturn->branch_id) {
                throw ValidationException::withMessages([
                    'items' => 'Batch return harus berasal dari branch yang sama.',
                ]);
            }

            if ((int) $batch->supplier_id !== (int) $purchaseReturn->supplier_id) {
                throw ValidationException::withMessages([
                    'items' => 'Batch return harus berasal dari supplier yang sama.',
                ]);
            }

            if ($itemPayload['quantity_returned'] > (float) $batch->quantity_available) {
                throw ValidationException::withMessages([
                    'items' => sprintf('Qty return untuk batch %s melebihi stok tersedia.', $batch->batch_number),
                ]);
            }

            $attributes = [
                'goods_receipt_item_id' => $batch->goodsReceiptItems->first()?->id,
                'medicine_batch_id' => $batch->id,
                'medicine_id' => $batch->medicine_id,
                'quantity_returned' => $itemPayload['quantity_returned'],
                'unit_cost' => $batch->purchase_cost ?? 0,
                'subtotal' => round($itemPayload['quantity_returned'] * (float) ($batch->purchase_cost ?? 0), 2),
                'reason' => $itemPayload['reason'],
                'notes' => $itemPayload['notes'],
            ];

            if ($itemPayload['id'] && $existingItems->has($itemPayload['id'])) {
                $record = $existingItems->get($itemPayload['id']);
                $record->update($attributes);
                $keptIds[] = $record->id;
                continue;
            }

            $record = $purchaseReturn->items()->create($attributes);
            $keptIds[] = $record->id;
        }

        $purchaseReturn->items()
            ->when($keptIds !== [], fn (Builder $query) => $query->whereNotIn('id', $keptIds))
            ->when($keptIds === [], fn (Builder $query) => $query)
            ->delete();
    }

    private function refreshTotals(PurchaseReturn $purchaseReturn): void
    {
        $purchaseReturn->update([
            'total_amount' => (float) $purchaseReturn->items()->sum('subtotal'),
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
                'supplier:id,code,name',
                'medicine:id,code,name,strength,base_unit',
                'goodsReceiptItems:id,medicine_batch_id',
            ])
            ->whereNotNull('supplier_id')
            ->orderBy('branch_id')
            ->orderBy('supplier_id')
            ->orderBy('expired_at')
            ->orderBy('received_at')
            ->get()
            ->map(function (MedicineBatch $batch): array {
                return [
                    'id' => (string) $batch->id,
                    'branch_id' => (string) $batch->branch_id,
                    'supplier_id' => (string) $batch->supplier_id,
                    'label' => trim(($batch->medicine?->code ?? '-') . ' - ' . ($batch->medicine?->name ?? '-') . ' | Batch ' . $batch->batch_number),
                    'branch_label' => trim(($batch->branch?->code ?? '-') . ' - ' . ($batch->branch?->name ?? '-')),
                    'supplier_label' => trim(($batch->supplier?->code ?? '-') . ' - ' . ($batch->supplier?->name ?? '-')),
                    'available_quantity' => (string) $batch->quantity_available,
                    'purchase_cost' => (string) ($batch->purchase_cost ?? 0),
                    'expired_at' => $batch->expired_at?->format('Y-m-d'),
                ];
            })
            ->values();
    }

    private function ensureDraft(PurchaseReturn $purchaseReturn, string $message): void
    {
        if ($purchaseReturn->status !== 'draft') {
            throw ValidationException::withMessages([
                'purchase_return' => $message,
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
            'supplier:id,code,name',
            'createdBy:id,name',
            'completedBy:id,name',
            'cancelledBy:id,name',
            'items.goodsReceiptItem:id,goods_receipt_id,medicine_batch_id',
            'items.medicine:id,code,name,strength,base_unit',
            'items.medicineBatch:id,branch_id,medicine_id,batch_number,quantity_available,purchase_cost,expired_at',
        ];
    }

    private function abilities(): array
    {
        return [
            'create' => auth()->check(),
            'edit' => auth()->check(),
            'complete' => auth()->check(),
            'cancel' => auth()->check(),
            'delete' => auth()->check(),
        ];
    }

    private function nextReturnNo(): string
    {
        $today = now()->format('Ymd');

        $lastReturn = PurchaseReturn::query()
            ->where('return_no', 'like', 'PR-' . $today . '-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $lastNumber = 0;

        if ($lastReturn && preg_match('/(\d+)$/', $lastReturn->return_no, $matches) === 1) {
            $lastNumber = (int) $matches[1];
        }

        return sprintf('PR-%s-%04d', $today, $lastNumber + 1);
    }
}
