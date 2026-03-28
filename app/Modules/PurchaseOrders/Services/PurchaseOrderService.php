<?php

namespace App\Modules\PurchaseOrders\Services;

use App\Models\Branch;
use App\Models\Medicine;
use App\Models\MedicineUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Services\NotificationCenterService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly NotificationCenterService $notificationCenterService,
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
            'purchaseOrders' => $this->table($filters),
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'code', 'name']),
            'supplierOptions' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'medicineOptions' => Medicine::query()
                ->with(['units' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('conversion_factor')])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'strength', 'base_unit']),
            'approvalThreshold' => $this->approvalThreshold(),
            'abilities' => $this->abilities(),
        ];
    }

    public function create(array $payload): PurchaseOrder
    {
        return DB::transaction(function () use ($payload): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()->create([
                'branch_id' => $payload['branch_id'],
                'supplier_id' => $payload['supplier_id'],
                'created_by_user_id' => auth()->id(),
                'po_no' => $this->nextPoNo(),
                'status' => 'draft',
                'order_date' => $payload['order_date'],
                'expected_date' => $payload['expected_date'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ]);

            $this->syncItems($purchaseOrder, $payload['items'] ?? []);
            $this->refreshTotals($purchaseOrder);

            $fresh = $purchaseOrder->fresh($this->relations());

            $this->auditLogService->log(
                'purchase_orders',
                'created',
                $fresh,
                'Purchase order baru dibuat.',
                [],
                $fresh?->toArray() ?? [],
            );

            return $fresh;
        });
    }

    public function update(PurchaseOrder $purchaseOrder, array $payload): PurchaseOrder
    {
        $this->ensureEditable($purchaseOrder);

        return DB::transaction(function () use ($purchaseOrder, $payload): PurchaseOrder {
            $before = $purchaseOrder->fresh($this->relations())?->toArray() ?? [];

            $purchaseOrder->update([
                'branch_id' => $payload['branch_id'],
                'supplier_id' => $payload['supplier_id'],
                'order_date' => $payload['order_date'],
                'expected_date' => $payload['expected_date'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ]);

            $this->syncItems($purchaseOrder, $payload['items'] ?? []);
            $this->refreshTotals($purchaseOrder);

            $fresh = $purchaseOrder->fresh($this->relations());

            $this->auditLogService->log(
                'purchase_orders',
                'updated',
                $fresh,
                'Purchase order diperbarui.',
                $before,
                $fresh?->toArray() ?? [],
            );

            return $fresh;
        });
    }

    public function submit(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if (! in_array($purchaseOrder->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'purchase_order' => 'Hanya purchase order draft atau rejected yang bisa disubmit ulang.',
            ]);
        }

        if (! $purchaseOrder->items()->exists()) {
            throw ValidationException::withMessages([
                'purchase_order' => 'Purchase order harus memiliki minimal satu item sebelum dikirim ke supplier.',
            ]);
        }

        $before = $purchaseOrder->fresh($this->relations())?->toArray() ?? [];
        $requiresApproval = (float) $purchaseOrder->total_amount >= $this->approvalThreshold();

        $purchaseOrder->update([
            'status' => $requiresApproval ? 'submitted' : 'ordered',
            'submitted_at' => now(),
            'submitted_by_user_id' => auth()->id(),
            'approved_at' => $requiresApproval ? null : now(),
            'approved_by_user_id' => $requiresApproval ? null : auth()->id(),
            'approval_notes' => $requiresApproval ? null : 'Auto-approved below configured threshold.',
            'rejected_at' => null,
            'rejected_by_user_id' => null,
            'rejection_reason' => null,
        ]);

        $fresh = $purchaseOrder->fresh($this->relations());

        $this->auditLogService->log(
            'purchase_orders',
            'submitted',
            $fresh,
            $requiresApproval
                ? 'Purchase order disubmit dan menunggu approval.'
                : 'Purchase order disubmit dan auto-approved karena di bawah threshold.',
            $before,
            $fresh?->toArray() ?? [],
            [
                'requires_approval' => $requiresApproval,
                'approval_threshold' => $this->approvalThreshold(),
            ],
        );

        if ($requiresApproval) {
            $this->notificationCenterService->notifyRoles(
                ['super-admin', 'clinic-admin'],
                [
                    'title' => 'PO awaiting approval',
                    'message' => sprintf('%s menunggu approval dengan total Rp %s.', $fresh->po_no, number_format((float) $fresh->total_amount, 0, ',', '.')),
                    'action_url' => route('purchase-orders') . '?search=' . urlencode($fresh->po_no),
                    'action_label' => 'Open purchase orders',
                    'module' => 'purchase_orders',
                    'level' => 'warning',
                    'meta' => [
                        'purchase_order_id' => $fresh->id,
                        'branch_id' => $fresh->branch_id,
                    ],
                ],
            );
        }

        return $fresh;
    }

    public function approve(PurchaseOrder $purchaseOrder, array $payload = []): PurchaseOrder
    {
        $this->ensureCanApprove();

        if ($purchaseOrder->status !== 'submitted') {
            throw ValidationException::withMessages([
                'purchase_order' => 'Hanya purchase order yang menunggu approval yang bisa di-approve.',
            ]);
        }

        $before = $purchaseOrder->fresh($this->relations())?->toArray() ?? [];

        $purchaseOrder->update([
            'status' => 'ordered',
            'approved_at' => now(),
            'approved_by_user_id' => auth()->id(),
            'approval_notes' => $payload['approval_notes'] ?? null,
        ]);

        $fresh = $purchaseOrder->fresh($this->relations());

        $this->auditLogService->log(
            'purchase_orders',
            'approved',
            $fresh,
            'Purchase order di-approve dan siap diterima supplier.',
            $before,
            $fresh?->toArray() ?? [],
            [
                'approval_notes' => $payload['approval_notes'] ?? null,
            ],
        );

        $this->notificationCenterService->notifyUsers(
            [$fresh->createdBy, $fresh->submittedBy],
            [
                'title' => 'PO approved',
                'message' => sprintf('%s sudah di-approve dan siap diproses ke supplier.', $fresh->po_no),
                'action_url' => route('purchase-orders') . '?search=' . urlencode($fresh->po_no),
                'action_label' => 'Open purchase orders',
                'module' => 'purchase_orders',
                'level' => 'success',
                'meta' => [
                    'purchase_order_id' => $fresh->id,
                    'branch_id' => $fresh->branch_id,
                ],
            ],
        );

        return $fresh;
    }

    public function reject(PurchaseOrder $purchaseOrder, array $payload): PurchaseOrder
    {
        $this->ensureCanApprove();

        if ($purchaseOrder->status !== 'submitted') {
            throw ValidationException::withMessages([
                'purchase_order' => 'Hanya purchase order yang menunggu approval yang bisa direject.',
            ]);
        }

        $before = $purchaseOrder->fresh($this->relations())?->toArray() ?? [];

        $purchaseOrder->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by_user_id' => auth()->id(),
            'rejection_reason' => $payload['rejection_reason'],
            'notes' => $payload['notes'] ?? $purchaseOrder->notes,
        ]);

        $fresh = $purchaseOrder->fresh($this->relations());

        $this->auditLogService->log(
            'purchase_orders',
            'rejected',
            $fresh,
            'Purchase order direject saat approval.',
            $before,
            $fresh?->toArray() ?? [],
            [
                'rejection_reason' => $payload['rejection_reason'],
            ],
        );

        $this->notificationCenterService->notifyUsers(
            [$fresh->createdBy, $fresh->submittedBy],
            [
                'title' => 'PO rejected',
                'message' => sprintf('%s direject. Alasan: %s', $fresh->po_no, $payload['rejection_reason']),
                'action_url' => route('purchase-orders') . '?search=' . urlencode($fresh->po_no),
                'action_label' => 'Open purchase orders',
                'module' => 'purchase_orders',
                'level' => 'danger',
                'meta' => [
                    'purchase_order_id' => $fresh->id,
                    'branch_id' => $fresh->branch_id,
                ],
            ],
        );

        return $fresh;
    }

    public function cancel(PurchaseOrder $purchaseOrder, array $payload): PurchaseOrder
    {
        if ($purchaseOrder->status === 'cancelled') {
            throw ValidationException::withMessages([
                'purchase_order' => 'Purchase order ini sudah dibatalkan.',
            ]);
        }

        if ($purchaseOrder->status === 'completed') {
            throw ValidationException::withMessages([
                'purchase_order' => 'Purchase order yang sudah selesai tidak bisa dibatalkan.',
            ]);
        }

        if ($purchaseOrder->goodsReceipts()->where('status', '!=', 'cancelled')->exists()) {
            throw ValidationException::withMessages([
                'purchase_order' => 'Purchase order yang sudah memiliki penerimaan barang aktif tidak bisa dibatalkan.',
            ]);
        }

        $before = $purchaseOrder->fresh($this->relations())?->toArray() ?? [];

        $purchaseOrder->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by_user_id' => auth()->id(),
            'cancel_reason' => $payload['cancel_reason'],
            'notes' => $payload['notes'] ?? $purchaseOrder->notes,
        ]);

        $fresh = $purchaseOrder->fresh($this->relations());

        $this->auditLogService->log(
            'purchase_orders',
            'cancelled',
            $fresh,
            'Purchase order dibatalkan.',
            $before,
            $fresh?->toArray() ?? [],
            [
                'cancel_reason' => $payload['cancel_reason'],
            ],
        );

        return $fresh;
    }

    public function delete(PurchaseOrder $purchaseOrder): void
    {
        $this->ensureEditable($purchaseOrder);

        if ($purchaseOrder->status !== 'draft') {
            throw ValidationException::withMessages([
                'purchase_order' => 'Hanya purchase order draft yang bisa diarsipkan.',
            ]);
        }

        $before = $purchaseOrder->fresh($this->relations())?->toArray() ?? [];

        $purchaseOrder->delete();

        $this->auditLogService->log(
            'purchase_orders',
            'archived',
            $purchaseOrder,
            'Purchase order draft diarsipkan.',
            $before,
            $purchaseOrder->fresh($this->relations())?->toArray() ?? [],
            [
                'purchase_order_id' => $purchaseOrder->id,
                'po_no' => $before['po_no'] ?? null,
            ],
        );
    }

    private function table(array $filters): LengthAwarePaginator
    {
        return PurchaseOrder::query()
            ->with($this->relations())
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('po_no', 'like', '%' . $filters['search'] . '%')
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
            ->when($filters['date'] !== '', fn (Builder $query) => $query->whereDate('order_date', $filters['date']))
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function syncItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        $items = collect($items)
            ->map(function (array $item): array {
                return [
                    'id' => filled($item['id'] ?? null) ? (int) $item['id'] : null,
                    'medicine_id' => (int) $item['medicine_id'],
                    'medicine_unit_id' => filled($item['medicine_unit_id'] ?? null) ? (int) $item['medicine_unit_id'] : null,
                    'quantity_ordered' => (float) $item['quantity_ordered'],
                    'unit_cost' => (float) $item['unit_cost'],
                    'notes' => $item['notes'] ?? null,
                ];
            })
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Purchase order harus memiliki minimal satu item.',
            ]);
        }

        $existingItems = $purchaseOrder->items()->get()->keyBy('id');
        $keptIds = [];

        foreach ($items as $itemPayload) {
            $medicine = Medicine::query()
                ->with(['units' => fn ($query) => $query->where('is_active', true)])
                ->findOrFail($itemPayload['medicine_id']);

            $unit = $this->resolvePurchaseUnit($medicine, $itemPayload['medicine_unit_id']);
            $quantityOrdered = (float) $itemPayload['quantity_ordered'];
            $unitCost = (float) $itemPayload['unit_cost'];
            $conversionFactor = (float) $unit->conversion_factor;
            $quantityOrderedBase = round($quantityOrdered * $conversionFactor, 2);
            $unitCostBase = round($unitCost / max($conversionFactor, 0.0001), 4);
            $subtotal = round($quantityOrdered * $unitCost, 2);

            if ($itemPayload['id'] && $existingItems->has($itemPayload['id'])) {
                $item = $existingItems->get($itemPayload['id']);
                $item->update([
                    'medicine_id' => $medicine->id,
                    'medicine_unit_id' => $unit->id,
                    'unit_label' => $unit->label,
                    'conversion_factor' => $conversionFactor,
                    'quantity_ordered' => $quantityOrdered,
                    'quantity_ordered_base' => $quantityOrderedBase,
                    'unit_cost' => $unitCost,
                    'unit_cost_base' => $unitCostBase,
                    'subtotal' => $subtotal,
                    'notes' => $itemPayload['notes'],
                ]);

                $keptIds[] = $item->id;
                continue;
            }

            $item = $purchaseOrder->items()->create([
                'medicine_id' => $medicine->id,
                'medicine_unit_id' => $unit->id,
                'unit_label' => $unit->label,
                'conversion_factor' => $conversionFactor,
                'quantity_ordered' => $quantityOrdered,
                'quantity_ordered_base' => $quantityOrderedBase,
                'unit_cost' => $unitCost,
                'unit_cost_base' => $unitCostBase,
                'subtotal' => $subtotal,
                'notes' => $itemPayload['notes'],
            ]);

            $keptIds[] = $item->id;
        }

        $purchaseOrder->items()
            ->when($keptIds !== [], fn (Builder $query) => $query->whereNotIn('id', $keptIds))
            ->when($keptIds === [], fn (Builder $query) => $query)
            ->delete();
    }

    private function refreshTotals(PurchaseOrder $purchaseOrder): void
    {
        $total = (float) $purchaseOrder->items()->sum('subtotal');

        $purchaseOrder->update([
            'total_amount' => $total,
        ]);
    }

    private function ensureEditable(PurchaseOrder $purchaseOrder): void
    {
        if (! in_array($purchaseOrder->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'purchase_order' => 'Hanya purchase order draft atau rejected yang bisa diubah.',
            ]);
        }

        if ($purchaseOrder->goodsReceipts()->where('status', '!=', 'cancelled')->exists()) {
            throw ValidationException::withMessages([
                'purchase_order' => 'Purchase order yang sudah memiliki penerimaan barang aktif tidak bisa diedit. Gunakan goods receipt untuk update penerimaan.',
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
            'submittedBy:id,name',
            'approvedBy:id,name',
            'rejectedBy:id,name',
            'cancelledBy:id,name',
            'items.medicine:id,code,name,strength,base_unit',
            'items.medicineUnit:id,medicine_id,label,conversion_factor',
            'items.goodsReceiptItems.goodsReceipt:id,status',
            'goodsReceipts:id,purchase_order_id,receipt_no,status,received_at,total_amount',
        ];
    }

    private function resolvePurchaseUnit(Medicine $medicine, ?int $unitId): MedicineUnit
    {
        if ($medicine->units->isEmpty()) {
            $medicine->ensureDefaultUnit();
            $medicine->load(['units' => fn ($query) => $query->where('is_active', true)]);
        }

        /** @var MedicineUnit|null $unit */
        $unit = $unitId
            ? $medicine->units->firstWhere('id', $unitId)
            : $medicine->units->first(fn (MedicineUnit $candidate): bool => $candidate->allow_purchase)
                ?? $medicine->units->first(fn (MedicineUnit $candidate): bool => $candidate->is_base);

        if (! $unit) {
            throw ValidationException::withMessages([
                'items' => sprintf('Medicine %s belum memiliki UOM purchase yang aktif.', $medicine->name),
            ]);
        }

        if (! $unit->allow_purchase) {
            throw ValidationException::withMessages([
                'items' => sprintf('UOM %s tidak diizinkan untuk purchase pada medicine %s.', $unit->label, $medicine->name),
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
            'submit' => auth()->check(),
            'approve' => auth()->user()?->can('approve purchase order management') ?? false,
            'cancel' => auth()->check(),
        ];
    }

    private function approvalThreshold(): float
    {
        return (float) config('procurement.purchase_order_approval_threshold', 5_000_000);
    }

    private function ensureCanApprove(): void
    {
        if (! (auth()->user()?->can('approve purchase order management') ?? false)) {
            throw ValidationException::withMessages([
                'purchase_order' => 'User ini tidak memiliki akses approval purchase order.',
            ]);
        }
    }

    private function nextPoNo(): string
    {
        $today = now()->format('Ymd');

        $lastPo = PurchaseOrder::query()
            ->where('po_no', 'like', 'PO-' . $today . '-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $lastNumber = 0;

        if ($lastPo && preg_match('/(\d+)$/', $lastPo->po_no, $matches) === 1) {
            $lastNumber = (int) $matches[1];
        }

        return sprintf('PO-%s-%04d', $today, $lastNumber + 1);
    }
}
