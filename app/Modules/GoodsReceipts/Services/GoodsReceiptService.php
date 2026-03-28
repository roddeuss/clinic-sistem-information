<?php

namespace App\Modules\GoodsReceipts\Services;

use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\MedicineBatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Services\ReorderPointService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceiptService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ReorderPointService $reorderPointService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'branch' => filled($filters['branch'] ?? null) ? (string) ($filters['branch']) : '',
            'supplier' => filled($filters['supplier'] ?? null) ? (string) ($filters['supplier']) : '',
            'status' => (string) ($filters['status'] ?? ''),
            'date' => (string) ($filters['date'] ?? ''),
            'purchase_order' => filled($filters['purchase_order'] ?? null) ? (string) ($filters['purchase_order']) : '',
        ];

        $receivablePurchaseOrders = $this->receivablePurchaseOrders();
        $prefilledPurchaseOrderId = $filters['purchase_order'] !== '' && $receivablePurchaseOrders->contains(fn (array $option): bool => $option['id'] === $filters['purchase_order'])
            ? $filters['purchase_order']
            : '';

        return [
            'filters' => $filters,
            'goodsReceipts' => $this->table($filters),
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'code', 'name']),
            'supplierOptions' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'purchaseOrderOptions' => $receivablePurchaseOrders,
            'prefilledPurchaseOrderId' => $prefilledPurchaseOrderId,
            'abilities' => $this->abilities(),
        ];
    }

    public function create(array $payload): GoodsReceipt
    {
        return DB::transaction(function () use ($payload): GoodsReceipt {
            $purchaseOrder = $this->loadReceivablePurchaseOrder((int) $payload['purchase_order_id']);

            $receipt = GoodsReceipt::query()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'branch_id' => $purchaseOrder->branch_id,
                'supplier_id' => $purchaseOrder->supplier_id,
                'received_by_user_id' => auth()->id(),
                'receipt_no' => $this->nextReceiptNo(),
                'status' => 'received',
                'received_at' => $payload['received_at'],
                'notes' => $payload['notes'] ?? null,
            ]);

            $this->syncItems($receipt, $purchaseOrder, $payload['items'] ?? []);
            $this->refreshTotals($receipt);
            $this->refreshPurchaseOrderStatus($purchaseOrder->fresh(['items.goodsReceiptItems.goodsReceipt']));

            $fresh = $receipt->fresh($this->relations());
            $this->refreshReorderForReceipt($fresh);

            $this->auditLogService->log(
                'goods_receipts',
                'created',
                $fresh,
                'Penerimaan barang baru dibuat.',
                [],
                $fresh?->toArray() ?? [],
            );

            return $fresh;
        });
    }

    public function update(GoodsReceipt $goodsReceipt, array $payload): GoodsReceipt
    {
        $this->ensureReceiptEditable($goodsReceipt);

        return DB::transaction(function () use ($goodsReceipt, $payload): GoodsReceipt {
            $purchaseOrder = $this->loadReceivablePurchaseOrder((int) $goodsReceipt->purchase_order_id, $goodsReceipt->id);
            $before = $goodsReceipt->fresh($this->relations())?->toArray() ?? [];

            $goodsReceipt->update([
                'received_at' => $payload['received_at'],
                'notes' => $payload['notes'] ?? null,
            ]);

            $this->syncItems($goodsReceipt, $purchaseOrder, $payload['items'] ?? []);
            $this->refreshTotals($goodsReceipt);
            $this->refreshPurchaseOrderStatus($purchaseOrder->fresh(['items.goodsReceiptItems.goodsReceipt']));

            $fresh = $goodsReceipt->fresh($this->relations());
            $this->refreshReorderForReceipt($fresh);

            $this->auditLogService->log(
                'goods_receipts',
                'updated',
                $fresh,
                'Penerimaan barang diperbarui.',
                $before,
                $fresh?->toArray() ?? [],
            );

            return $fresh;
        });
    }

    public function cancel(GoodsReceipt $goodsReceipt, array $payload): GoodsReceipt
    {
        $this->ensureReceiptEditable($goodsReceipt);

        return DB::transaction(function () use ($goodsReceipt, $payload): GoodsReceipt {
            $before = $goodsReceipt->fresh($this->relations())?->toArray() ?? [];

            $goodsReceipt->loadMissing('items.medicineBatch.dispenseBatches', 'purchaseOrder.items.goodsReceiptItems.goodsReceipt');

            foreach ($goodsReceipt->items as $item) {
                $batch = $item->medicineBatch;

                if ($batch?->dispenseBatches()->exists()) {
                    throw ValidationException::withMessages([
                        'goods_receipt' => 'Goods receipt yang batch-nya sudah dipakai dispense tidak bisa dibatalkan.',
                    ]);
                }

                if ($batch) {
                    $batch->delete();
                }
            }

            $goodsReceipt->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by_user_id' => auth()->id(),
                'cancel_reason' => $payload['cancel_reason'],
                'notes' => $payload['notes'] ?? $goodsReceipt->notes,
            ]);

            $this->refreshPurchaseOrderStatus($goodsReceipt->purchaseOrder->fresh(['items.goodsReceiptItems.goodsReceipt']));

            $fresh = $goodsReceipt->fresh($this->relations());
            $this->refreshReorderForReceipt($fresh);

            $this->auditLogService->log(
                'goods_receipts',
                'cancelled',
                $fresh,
                'Penerimaan barang dibatalkan.',
                $before,
                $fresh?->toArray() ?? [],
                [
                    'cancel_reason' => $payload['cancel_reason'],
                ],
            );

            return $fresh;
        });
    }

    private function table(array $filters): LengthAwarePaginator
    {
        return GoodsReceipt::query()
            ->with($this->relations())
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('receipt_no', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('purchaseOrder', fn (Builder $poQuery) => $poQuery->where('po_no', 'like', '%' . $filters['search'] . '%'))
                        ->orWhereHas('supplier', fn (Builder $supplierQuery) => $supplierQuery->where('name', 'like', '%' . $filters['search'] . '%'))
                        ->orWhereHas('items.medicine', function (Builder $medicineQuery) use ($filters): void {
                            $medicineQuery
                                ->where('code', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('name', 'like', '%' . $filters['search'] . '%');
                        });
                });
            })
            ->when($filters['branch'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->when($filters['supplier'] !== '', fn (Builder $query) => $query->where('supplier_id', $filters['supplier']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['date'] !== '', fn (Builder $query) => $query->whereDate('received_at', $filters['date']))
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function receivablePurchaseOrders(): Collection
    {
        return PurchaseOrder::query()
            ->with([
                'branch:id,code,name',
                'supplier:id,code,name',
                'items.medicine:id,code,name,strength,base_unit',
                'items.medicineUnit:id,medicine_id,label,conversion_factor',
                'items.goodsReceiptItems.goodsReceipt:id,status',
            ])
            ->whereIn('status', ['ordered', 'partial_received'])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (PurchaseOrder $purchaseOrder): array {
                $items = $purchaseOrder->items
                    ->map(function (PurchaseOrderItem $item): array {
                        $conversionFactor = (float) ($item->conversion_factor ?: 1);
                        $receivedQuantityBase = $this->receivedQuantityForItem($item);
                        $receivedQuantity = round($receivedQuantityBase / max($conversionFactor, 0.0001), 2);
                        $outstandingQuantityBase = max(0, (float) $item->quantity_ordered_base - $receivedQuantityBase);
                        $outstandingQuantity = round($outstandingQuantityBase / max($conversionFactor, 0.0001), 2);

                        return [
                            'purchase_order_item_id' => (string) $item->id,
                            'medicine_id' => (string) $item->medicine_id,
                            'medicine_label' => trim(($item->medicine?->code ?? '-') . ' - ' . ($item->medicine?->name ?? '-') . ($item->medicine?->strength ? ' | ' . $item->medicine->strength : '')),
                            'quantity_ordered' => (string) $item->quantity_ordered,
                            'quantity_ordered_base' => (string) $item->quantity_ordered_base,
                            'received_quantity' => (string) $receivedQuantity,
                            'received_quantity_base' => (string) $receivedQuantityBase,
                            'outstanding_quantity' => (string) $outstandingQuantity,
                            'outstanding_quantity_base' => (string) $outstandingQuantityBase,
                            'unit_cost' => (string) $item->unit_cost,
                            'unit_cost_base' => (string) $item->unit_cost_base,
                            'base_unit' => $item->medicine?->base_unit,
                            'unit_label' => $item->unit_label ?: ($item->medicineUnit?->label ?? $item->medicine?->base_unit),
                            'conversion_factor' => (string) $item->conversion_factor,
                        ];
                    })
                    ->filter(fn (array $item): bool => (float) $item['outstanding_quantity'] > 0)
                    ->values()
                    ->all();

                return [
                    'id' => (string) $purchaseOrder->id,
                    'po_no' => $purchaseOrder->po_no,
                    'branch_id' => (string) $purchaseOrder->branch_id,
                    'branch_label' => trim(($purchaseOrder->branch?->code ?? '-') . ' - ' . ($purchaseOrder->branch?->name ?? '-')),
                    'supplier_id' => (string) $purchaseOrder->supplier_id,
                    'supplier_label' => trim(($purchaseOrder->supplier?->code ?? '-') . ' - ' . ($purchaseOrder->supplier?->name ?? '-')),
                    'order_date' => $purchaseOrder->order_date?->format('Y-m-d'),
                    'expected_date' => $purchaseOrder->expected_date?->format('Y-m-d'),
                    'items' => $items,
                ];
            })
            ->filter(fn (array $purchaseOrder): bool => $purchaseOrder['items'] !== [])
            ->values();
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function syncItems(GoodsReceipt $goodsReceipt, PurchaseOrder $purchaseOrder, array $items): void
    {
        $existingItems = $goodsReceipt->items()->with('medicineBatch.dispenseBatches')->get()->keyBy('id');
        $purchaseOrderItems = $purchaseOrder->items()->with(['goodsReceiptItems.goodsReceipt', 'medicine'])->get()->keyBy('id');
        $supplierName = $purchaseOrder->supplier?->name;

        $items = collect($items)
            ->map(function (array $item): array {
                return [
                    'id' => filled($item['id'] ?? null) ? (int) $item['id'] : null,
                    'purchase_order_item_id' => (int) $item['purchase_order_item_id'],
                    'batch_number' => strtoupper(trim((string) ($item['batch_number'] ?? ''))),
                    'expired_at' => $item['expired_at'] ?? null,
                    'quantity_received' => filled($item['quantity_received'] ?? null) ? (float) $item['quantity_received'] : 0.0,
                    'unit_cost' => filled($item['unit_cost'] ?? null) ? (float) $item['unit_cost'] : 0.0,
                    'notes' => $item['notes'] ?? null,
                ];
            })
            ->filter(fn (array $item): bool => $item['quantity_received'] > 0)
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Minimal satu item harus diterima pada goods receipt.',
            ]);
        }

        $keptIds = [];

        foreach ($items as $itemPayload) {
            /** @var PurchaseOrderItem|null $purchaseOrderItem */
            $purchaseOrderItem = $purchaseOrderItems->get($itemPayload['purchase_order_item_id']);

            if (! $purchaseOrderItem) {
                throw ValidationException::withMessages([
                    'items' => 'Ada item penerimaan yang tidak berasal dari purchase order terpilih.',
                ]);
            }

            $existingItem = $itemPayload['id'] ? $existingItems->get($itemPayload['id']) : null;
            $conversionFactor = (float) ($purchaseOrderItem->conversion_factor ?: 1);
            $quantityReceivedBase = round((float) $itemPayload['quantity_received'] * $conversionFactor, 2);
            $unitCostBase = round((float) $itemPayload['unit_cost'] / max($conversionFactor, 0.0001), 4);
            $subtotal = round((float) $itemPayload['quantity_received'] * (float) $itemPayload['unit_cost'], 2);

            $allowedQuantity = $this->allowedQuantityForReceiptItem($purchaseOrderItem, $existingItem?->id);

            if ($quantityReceivedBase > $allowedQuantity) {
                throw ValidationException::withMessages([
                    'items' => sprintf(
                        'Qty terima untuk %s melebihi outstanding PO. Maksimal %.2f.',
                        $purchaseOrderItem->medicine?->name ?? 'item',
                        round($allowedQuantity / max($conversionFactor, 0.0001), 2),
                    ),
                ]);
            }

            if ($existingItem) {
                $this->ensureReceiptItemEditable($existingItem);
                $batch = $existingItem->medicineBatch;

                if (! $batch) {
                    $batch = new MedicineBatch();
                }

                $batch->fill([
                    'branch_id' => $goodsReceipt->branch_id,
                    'medicine_id' => $purchaseOrderItem->medicine_id,
                    'supplier_id' => $goodsReceipt->supplier_id,
                    'batch_number' => $itemPayload['batch_number'],
                    'received_at' => $goodsReceipt->received_at,
                    'expired_at' => $itemPayload['expired_at'],
                    'quantity_received' => $quantityReceivedBase,
                    'quantity_available' => $quantityReceivedBase,
                    'purchase_cost' => $unitCostBase,
                    'supplier_name' => $supplierName,
                    'notes' => $itemPayload['notes'],
                    'is_active' => true,
                ]);
                $batch->save();

                $existingItem->update([
                    'purchase_order_item_id' => $purchaseOrderItem->id,
                    'medicine_id' => $purchaseOrderItem->medicine_id,
                    'medicine_unit_id' => $purchaseOrderItem->medicine_unit_id,
                    'medicine_batch_id' => $batch->id,
                    'unit_label' => $purchaseOrderItem->unit_label,
                    'conversion_factor' => $conversionFactor,
                    'batch_number' => $itemPayload['batch_number'],
                    'expired_at' => $itemPayload['expired_at'],
                    'quantity_received' => $itemPayload['quantity_received'],
                    'quantity_received_base' => $quantityReceivedBase,
                    'unit_cost' => $itemPayload['unit_cost'],
                    'unit_cost_base' => $unitCostBase,
                    'subtotal' => $subtotal,
                    'notes' => $itemPayload['notes'],
                ]);

                $keptIds[] = $existingItem->id;
                continue;
            }

            $batch = MedicineBatch::query()->create([
                'branch_id' => $goodsReceipt->branch_id,
                'medicine_id' => $purchaseOrderItem->medicine_id,
                'supplier_id' => $goodsReceipt->supplier_id,
                'batch_number' => $itemPayload['batch_number'],
                'received_at' => $goodsReceipt->received_at,
                'expired_at' => $itemPayload['expired_at'],
                'quantity_received' => $quantityReceivedBase,
                'quantity_available' => $quantityReceivedBase,
                'purchase_cost' => $unitCostBase,
                'supplier_name' => $supplierName,
                'notes' => $itemPayload['notes'],
                'is_active' => true,
            ]);

            $item = $goodsReceipt->items()->create([
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'medicine_id' => $purchaseOrderItem->medicine_id,
                'medicine_unit_id' => $purchaseOrderItem->medicine_unit_id,
                'medicine_batch_id' => $batch->id,
                'unit_label' => $purchaseOrderItem->unit_label,
                'conversion_factor' => $conversionFactor,
                'batch_number' => $itemPayload['batch_number'],
                'expired_at' => $itemPayload['expired_at'],
                'quantity_received' => $itemPayload['quantity_received'],
                'quantity_received_base' => $quantityReceivedBase,
                'unit_cost' => $itemPayload['unit_cost'],
                'unit_cost_base' => $unitCostBase,
                'subtotal' => $subtotal,
                'notes' => $itemPayload['notes'],
            ]);

            $keptIds[] = $item->id;
        }

        foreach ($existingItems as $existingItem) {
            if (in_array($existingItem->id, $keptIds, true)) {
                continue;
            }

            $this->ensureReceiptItemEditable($existingItem);

            if ($existingItem->medicineBatch) {
                $existingItem->medicineBatch->delete();
            }

            $existingItem->delete();
        }
    }

    private function refreshTotals(GoodsReceipt $goodsReceipt): void
    {
        $goodsReceipt->update([
            'total_amount' => (float) $goodsReceipt->items()->sum('subtotal'),
        ]);
    }

    private function refreshPurchaseOrderStatus(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->status === 'cancelled') {
            return;
        }

        $purchaseOrder->loadMissing('items.goodsReceiptItems.goodsReceipt');

        $orderedQuantity = (float) $purchaseOrder->items->sum(fn (PurchaseOrderItem $item) => (float) $item->quantity_ordered_base);
        $receivedQuantity = (float) $purchaseOrder->items->sum(fn (PurchaseOrderItem $item) => $this->receivedQuantityForItem($item));

        $nextStatus = $purchaseOrder->submitted_at ? 'ordered' : 'draft';

        if ($receivedQuantity > 0 && $receivedQuantity < $orderedQuantity) {
            $nextStatus = 'partial_received';
        } elseif ($orderedQuantity > 0 && $receivedQuantity >= $orderedQuantity) {
            $nextStatus = 'completed';
        }

        if ($purchaseOrder->status !== $nextStatus) {
            $purchaseOrder->update([
                'status' => $nextStatus,
            ]);
        }
    }

    private function loadReceivablePurchaseOrder(int $purchaseOrderId, ?int $currentReceiptId = null): PurchaseOrder
    {
        $purchaseOrder = PurchaseOrder::query()
            ->with([
                'supplier:id,code,name',
                'items.medicine:id,code,name,strength,base_unit',
                'items.medicineUnit:id,medicine_id,label,conversion_factor',
                'items.goodsReceiptItems.goodsReceipt:id,status',
            ])
            ->findOrFail($purchaseOrderId);

        if (! in_array($purchaseOrder->status, ['ordered', 'partial_received'], true) && ! $currentReceiptId) {
            throw ValidationException::withMessages([
                'purchase_order_id' => 'Purchase order ini belum siap untuk penerimaan barang.',
            ]);
        }

        if ($purchaseOrder->status === 'cancelled') {
            throw ValidationException::withMessages([
                'purchase_order_id' => 'Purchase order yang dibatalkan tidak bisa diterima.',
            ]);
        }

        if ($purchaseOrder->status === 'completed' && ! $currentReceiptId) {
            throw ValidationException::withMessages([
                'purchase_order_id' => 'Purchase order ini sudah lengkap diterima.',
            ]);
        }

        return $purchaseOrder;
    }

    private function ensureReceiptEditable(GoodsReceipt $goodsReceipt): void
    {
        if ($goodsReceipt->status === 'cancelled') {
            throw ValidationException::withMessages([
                'goods_receipt' => 'Goods receipt yang sudah dibatalkan tidak bisa diubah.',
            ]);
        }
    }

    private function ensureReceiptItemEditable(GoodsReceiptItem $item): void
    {
        if ($item->medicineBatch?->dispenseBatches()->exists()) {
            throw ValidationException::withMessages([
                'goods_receipt' => 'Goods receipt dengan batch yang sudah dipakai dispense tidak bisa diubah.',
            ]);
        }
    }

    private function allowedQuantityForReceiptItem(PurchaseOrderItem $purchaseOrderItem, ?int $currentReceiptItemId = null): float
    {
        $receivedQuantity = $purchaseOrderItem->goodsReceiptItems
            ->filter(function (GoodsReceiptItem $receiptItem) use ($currentReceiptItemId): bool {
                if ($currentReceiptItemId !== null && $receiptItem->id === $currentReceiptItemId) {
                    return false;
                }

                return $receiptItem->goodsReceipt?->status !== 'cancelled';
            })
            ->sum(fn (GoodsReceiptItem $receiptItem) => (float) $receiptItem->quantity_received_base);

        return max(0, (float) $purchaseOrderItem->quantity_ordered_base - $receivedQuantity);
    }

    private function receivedQuantityForItem(PurchaseOrderItem $purchaseOrderItem): float
    {
        return (float) $purchaseOrderItem->goodsReceiptItems
            ->filter(fn (GoodsReceiptItem $receiptItem): bool => $receiptItem->goodsReceipt?->status !== 'cancelled')
            ->sum(fn (GoodsReceiptItem $receiptItem) => (float) $receiptItem->quantity_received_base);
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'purchaseOrder:id,po_no,status',
            'branch:id,code,name',
            'supplier:id,code,name',
            'receivedBy:id,name',
            'cancelledBy:id,name',
            'items.purchaseOrderItem:id,purchase_order_id,medicine_id,medicine_unit_id,unit_label,conversion_factor,quantity_ordered,quantity_ordered_base,unit_cost,unit_cost_base',
            'items.purchaseOrderItem.goodsReceiptItems.goodsReceipt:id,status',
            'items.medicine:id,code,name,strength,base_unit',
            'items.medicineUnit:id,medicine_id,label,conversion_factor',
            'items.medicineBatch:id,branch_id,medicine_id,batch_number,quantity_received,quantity_available,purchase_cost',
        ];
    }

    private function abilities(): array
    {
        return [
            'create' => auth()->check(),
            'edit' => auth()->check(),
            'cancel' => auth()->check(),
        ];
    }

    private function nextReceiptNo(): string
    {
        $today = now()->format('Ymd');

        $lastReceipt = GoodsReceipt::query()
            ->where('receipt_no', 'like', 'GR-' . $today . '-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $lastNumber = 0;

        if ($lastReceipt && preg_match('/(\d+)$/', $lastReceipt->receipt_no, $matches) === 1) {
            $lastNumber = (int) $matches[1];
        }

        return sprintf('GR-%s-%04d', $today, $lastNumber + 1);
    }

    private function refreshReorderForReceipt(GoodsReceipt $goodsReceipt): void
    {
        $goodsReceipt->loadMissing('items.medicineBatch:id,medicine_id,branch_id');

        $goodsReceipt->items
            ->map(fn (GoodsReceiptItem $item) => $item->medicineBatch)
            ->filter()
            ->unique(fn (MedicineBatch $batch) => sprintf('%s:%s', $batch->branch_id, $batch->medicine_id))
            ->each(fn (MedicineBatch $batch) => $this->reorderPointService->refreshForMedicineBranch((int) $batch->medicine_id, (int) $batch->branch_id));
    }
}
