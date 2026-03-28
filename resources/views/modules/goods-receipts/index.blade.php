@php
    $receiptModalOpen = $errors->any() && in_array(old('form_context'), ['goods-receipt-create', 'goods-receipt-update'], true);
    $cancelModalOpen = $errors->any() && old('form_context') === 'goods-receipt-cancel';
    $receiptModalMode = old('form_context') === 'goods-receipt-update' ? 'update' : 'create';
    $defaultPurchaseOrderId = old('purchase_order_id', $prefilledPurchaseOrderId);

    $oldItems = collect(old('items', []))
        ->map(fn ($item) => [
            'id' => $item['id'] ?? null,
            'purchase_order_item_id' => (string) ($item['purchase_order_item_id'] ?? ''),
            'medicine_label' => $item['medicine_label'] ?? '',
            'unit_label' => $item['unit_label'] ?? '',
            'base_unit' => $item['base_unit'] ?? '',
            'conversion_factor' => $item['conversion_factor'] ?? '1',
            'ordered_quantity' => $item['ordered_quantity'] ?? '',
            'ordered_quantity_base' => $item['ordered_quantity_base'] ?? '',
            'received_quantity' => $item['received_quantity'] ?? '',
            'received_quantity_base' => $item['received_quantity_base'] ?? '',
            'outstanding_quantity' => $item['outstanding_quantity'] ?? '',
            'outstanding_quantity_base' => $item['outstanding_quantity_base'] ?? '',
            'batch_number' => $item['batch_number'] ?? '',
            'expired_at' => $item['expired_at'] ?? '',
            'quantity_received_now' => $item['quantity_received'] ?? '',
            'unit_cost' => $item['unit_cost'] ?? '',
            'notes' => $item['notes'] ?? '',
        ])
        ->values()
        ->all();

    $receiptModalForm = [
        'id' => old('entity_id'),
        'receipt_no' => old('receipt_no', ''),
        'purchase_order_id' => (string) $defaultPurchaseOrderId,
        'received_at' => old('received_at', now()->toDateString()),
        'notes' => old('notes', ''),
        'items' => $oldItems,
    ];

    $cancelModalForm = [
        'id' => old('entity_id'),
        'receipt_no' => old('receipt_no', ''),
        'purchase_order_no' => old('purchase_order_no', ''),
        'cancel_reason' => old('cancel_reason', ''),
        'notes' => old('notes', ''),
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Goods Receipts" />

    <div
        x-data="{
            receiptModalOpen: @js($receiptModalOpen || $prefilledPurchaseOrderId !== ''),
            cancelModalOpen: @js($cancelModalOpen),
            receiptMode: @js($receiptModalMode),
            storeAction: @js(route('goods-receipts.store')),
            updateBase: @js(url('/goods-receipts')),
            purchaseOrders: @js($purchaseOrderOptions),
            form: @js($receiptModalForm),
            cancelForm: @js($cancelModalForm),
            emptyForm() {
                return { id: null, receipt_no: '', purchase_order_id: @js((string) $prefilledPurchaseOrderId), received_at: @js(now()->toDateString()), notes: '', items: [] };
            },
            selectedPurchaseOrder() {
                return this.purchaseOrders.find(item => item.id === this.form.purchase_order_id) ?? null;
            },
            syncItemsFromPurchaseOrder() {
                const selected = this.selectedPurchaseOrder();
                if (!selected) {
                    this.form.items = [];
                    return;
                }

                this.form.items = selected.items.map(item => ({
                    id: null,
                    purchase_order_item_id: item.purchase_order_item_id,
                    medicine_label: item.medicine_label,
                    unit_label: item.unit_label,
                    base_unit: item.base_unit,
                    conversion_factor: item.conversion_factor,
                    ordered_quantity: item.quantity_ordered,
                    ordered_quantity_base: item.quantity_ordered_base,
                    received_quantity: item.received_quantity,
                    received_quantity_base: item.received_quantity_base,
                    outstanding_quantity: item.outstanding_quantity,
                    outstanding_quantity_base: item.outstanding_quantity_base,
                    batch_number: '',
                    expired_at: '',
                    quantity_received_now: item.outstanding_quantity,
                    unit_cost: item.unit_cost,
                    notes: '',
                }));
            },
            openCreate(prefilled = null) {
                this.receiptMode = 'create';
                this.form = this.emptyForm();
                if (prefilled) {
                    this.form.purchase_order_id = prefilled;
                }
                this.syncItemsFromPurchaseOrder();
                this.receiptModalOpen = true;
            },
            openEdit(payload) {
                this.receiptMode = 'update';
                this.form = payload;
                this.receiptModalOpen = true;
            },
            money(value) { return new Intl.NumberFormat('id-ID').format(Number(value || 0)); },
            itemSubtotal(item) { return Number(item.quantity_received_now || 0) * Number(item.unit_cost || 0); },
            baseReceiptQuantity(item) { return Number(item.quantity_received_now || 0) * Number(item.conversion_factor || 1); },
            receiptTotal() { return (this.form.items || []).reduce((carry, item) => carry + this.itemSubtotal(item), 0); },
            openCancel(payload) { this.cancelForm = payload; this.cancelModalOpen = true; },
            init() {
                if (this.receiptModalOpen && this.receiptMode === 'create' && this.form.purchase_order_id && this.form.items.length === 0) {
                    this.syncItemsFromPurchaseOrder();
                }
            },
        }"
        @keydown.escape.window="receiptModalOpen = false; cancelModalOpen = false"
        class="space-y-6"
    >
        @if (session('status'))
            <x-ui.alert variant="success" :message="session('status')" />
        @endif
        @if ($errors->any())
            <x-ui.alert variant="error" :message="$errors->first()" />
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Goods Receipts</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Penerimaan barang dari supplier berdasarkan purchase order. Setiap receipt item akan membuat batch baru
                        ke inventory branch yang dipilih pada PO.
                    </p>
                </div>
                @if ($abilities['create'])
                    <x-ui.button type="button" x-on:click="openCreate()">Tambah Goods Receipt</x-ui.button>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('goods-receipts') }}" class="grid gap-4 md:grid-cols-[1.1fr_220px_220px_180px_180px_auto]">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari nomor receipt, PO, supplier, atau medicine" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <select name="branch" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Semua branch</option>
                    @foreach ($branchOptions as $branchOption)
                        <option value="{{ $branchOption->id }}" @selected($filters['branch'] === (string) $branchOption->id)>{{ $branchOption->code }} - {{ $branchOption->name }}</option>
                    @endforeach
                </select>
                <select name="supplier" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Semua supplier</option>
                    @foreach ($supplierOptions as $supplierOption)
                        <option value="{{ $supplierOption->id }}" @selected($filters['supplier'] === (string) $supplierOption->id)>{{ $supplierOption->code }} - {{ $supplierOption->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Semua status</option>
                    <option value="received" @selected($filters['status'] === 'received')>Received</option>
                    <option value="cancelled" @selected($filters['status'] === 'cancelled')>Cancelled</option>
                </select>
                <input type="date" name="date" value="{{ $filters['date'] }}" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <div class="flex gap-3">
                    <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
                    <a href="{{ route('goods-receipts') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Goods receipt list</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $goodsReceipts->firstItem() ?? 0 }} - {{ $goodsReceipts->lastItem() ?? 0 }} dari {{ $goodsReceipts->total() }} receipt.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Receipt</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">PO & Supplier</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Items</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Total</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($goodsReceipts as $goodsReceipt)
                            @php
                                $editPayload = [
                                    'id' => $goodsReceipt->id,
                                    'receipt_no' => $goodsReceipt->receipt_no,
                                    'purchase_order_id' => (string) $goodsReceipt->purchase_order_id,
                                    'received_at' => $goodsReceipt->received_at?->format('Y-m-d'),
                                    'notes' => $goodsReceipt->notes ?? '',
                                    'items' => $goodsReceipt->items->map(function ($item) {
                                        $conversionFactor = (float) ($item->conversion_factor ?: 1);
                                        $receivedBaseBeforeCurrent = $item->purchaseOrderItem
                                            ? (float) $item->purchaseOrderItem->goodsReceiptItems
                                                ->filter(fn ($receiptItem) => $receiptItem->goodsReceipt?->status !== 'cancelled' && $receiptItem->id !== $item->id)
                                                ->sum(fn ($receiptItem) => (float) $receiptItem->quantity_received_base)
                                            : 0;

                                        $orderedBase = (float) ($item->purchaseOrderItem?->quantity_ordered_base ?? 0);
                                        $outstandingBase = max(0, $orderedBase - $receivedBaseBeforeCurrent);

                                        return [
                                            'id' => $item->id,
                                            'purchase_order_item_id' => (string) $item->purchase_order_item_id,
                                            'medicine_label' => trim(($item->medicine?->code ?? '-') . ' - ' . ($item->medicine?->name ?? '-') . ($item->medicine?->strength ? ' | ' . $item->medicine->strength : '')),
                                            'unit_label' => $item->unit_label ?: ($item->purchaseOrderItem?->unit_label ?? $item->medicine?->base_unit),
                                            'base_unit' => $item->medicine?->base_unit,
                                            'conversion_factor' => (string) $conversionFactor,
                                            'ordered_quantity' => (string) ($item->purchaseOrderItem?->quantity_ordered ?? ''),
                                            'ordered_quantity_base' => (string) $orderedBase,
                                            'received_quantity' => (string) round($receivedBaseBeforeCurrent / max($conversionFactor, 0.0001), 2),
                                            'received_quantity_base' => (string) $receivedBaseBeforeCurrent,
                                            'outstanding_quantity' => (string) round($outstandingBase / max($conversionFactor, 0.0001), 2),
                                            'outstanding_quantity_base' => (string) $outstandingBase,
                                            'batch_number' => $item->batch_number,
                                            'expired_at' => $item->expired_at?->format('Y-m-d') ?? '',
                                            'quantity_received_now' => (string) $item->quantity_received,
                                            'unit_cost' => (string) $item->unit_cost,
                                            'notes' => $item->notes ?? '',
                                        ];
                                    })->values()->all(),
                                ];

                                $cancelPayload = [
                                    'id' => $goodsReceipt->id,
                                    'receipt_no' => $goodsReceipt->receipt_no,
                                    'purchase_order_no' => $goodsReceipt->purchaseOrder?->po_no ?? '-',
                                    'cancel_reason' => '',
                                    'notes' => $goodsReceipt->notes ?? '',
                                ];

                                $statusClasses = $goodsReceipt->status === 'received'
                                    ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300'
                                    : 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-300';
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $goodsReceipt->receipt_no }}</div>
                                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $goodsReceipt->branch?->code }} - {{ $goodsReceipt->branch?->name }}</div>
                                    <div class="mt-1 text-xs text-gray-400">Received {{ $goodsReceipt->received_at?->format('d M Y') }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $goodsReceipt->purchaseOrder?->po_no }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $goodsReceipt->supplier?->code }} - {{ $goodsReceipt->supplier?->name }}</div>
                                    @if ($goodsReceipt->notes)
                                        <div class="mt-1 text-xs text-gray-400">{{ $goodsReceipt->notes }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $goodsReceipt->items->count() }} item</div>
                                    @foreach ($goodsReceipt->items->take(2) as $item)
                                        <div class="mt-1 text-xs text-gray-400">{{ $item->medicine?->code }} | {{ $item->batch_number }} | {{ number_format((float) $item->quantity_received, 2) }} {{ $item->unit_label ?: $item->medicine?->base_unit }}</div>
                                    @endforeach
                                    @if ($goodsReceipt->items->count() > 2)
                                        <div class="mt-1 text-xs text-gray-400">+{{ $goodsReceipt->items->count() - 2 }} item lain</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Rp {{ number_format((float) $goodsReceipt->total_amount, 0, ',', '.') }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses }}">{{ ucfirst($goodsReceipt->status) }}</span>
                                    @if ($goodsReceipt->cancel_reason)
                                        <div class="mt-2 text-xs text-gray-400">{{ $goodsReceipt->cancel_reason }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'] && $goodsReceipt->status === 'received')
                                            <button type="button" title="Edit goods receipt" data-payload='@json($editPayload)' x-on:click='openEdit(JSON.parse($el.dataset.payload))' class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </button>
                                        @endif
                                        @if ($abilities['cancel'] && $goodsReceipt->status === 'received')
                                            <button type="button" title="Batalkan goods receipt" data-payload='@json($cancelPayload)' x-on:click='openCancel(JSON.parse($el.dataset.payload))' class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-amber-200 text-amber-600 transition hover:bg-amber-50 dark:border-amber-500/20 dark:text-amber-300 dark:hover:bg-amber-500/10">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 8V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12 16H12.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10.29 3.86L1.82 18A2 2 0 0 0 3.53 21H20.47A2 2 0 0 0 22.18 18L13.71 3.86A2 2 0 0 0 10.29 3.86Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada goods receipt.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $goodsReceipts->links() }}</div>
        </section>

        <x-ui.modal show="receiptModalOpen" maxWidth="6xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="receiptMode === 'create' ? 'Tambah Goods Receipt' : 'Update Goods Receipt'"></h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pilih purchase order lalu isi batch, expiry, dan qty yang benar-benar diterima.</p>
                </div>
                <button type="button" x-on:click="receiptModalOpen = false" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </button>
            </div>
            <form method="POST" x-bind:action="receiptMode === 'create' ? storeAction : `${updateBase}/${form.id}`" class="space-y-6 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="receiptMode === 'create' ? 'goods-receipt-create' : 'goods-receipt-update'">
                <input type="hidden" name="entity_id" x-bind:value="form.id">
                <input type="hidden" name="receipt_no" x-bind:value="form.receipt_no">
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Purchase order</label>
                        <select x-model="form.purchase_order_id" name="purchase_order_id" x-on:change="if (receiptMode === 'create') syncItemsFromPurchaseOrder()" x-bind:disabled="receiptMode === 'update'" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Pilih purchase order</option>
                            <template x-for="purchaseOrder in purchaseOrders" :key="purchaseOrder.id">
                                <option x-bind:value="purchaseOrder.id" x-text="`${purchaseOrder.po_no} | ${purchaseOrder.supplier_label}`"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Received date</label>
                        <input x-model="form.received_at" type="date" name="received_at" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-2" x-show="selectedPurchaseOrder()">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="text-xs uppercase tracking-[0.14em] text-gray-400">Branch</div>
                        <div class="mt-2 font-medium text-gray-900 dark:text-white" x-text="selectedPurchaseOrder()?.branch_label || '-'"></div>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="text-xs uppercase tracking-[0.14em] text-gray-400">Supplier</div>
                        <div class="mt-2 font-medium text-gray-900 dark:text-white" x-text="selectedPurchaseOrder()?.supplier_label || '-'"></div>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                    <textarea x-model="form.notes" name="notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                </div>

                <div class="rounded-2xl border border-gray-200 dark:border-gray-800">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <h4 class="font-medium text-gray-900 dark:text-white">Receipt items</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Qty maksimal mengikuti outstanding dari purchase order.</p>
                    </div>
                    <div class="space-y-4 p-5">
                        <template x-if="form.items.length === 0">
                            <div class="rounded-2xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                Pilih purchase order untuk memuat item yang masih outstanding.
                            </div>
                        </template>
                        <template x-for="(item, index) in form.items" :key="`${index}-${item.purchase_order_item_id}`">
                            <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                                <input type="hidden" x-bind:name="`items[${index}][id]`" x-bind:value="item.id">
                                <input type="hidden" x-bind:name="`items[${index}][purchase_order_item_id]`" x-bind:value="item.purchase_order_item_id">
                                <input type="hidden" x-bind:name="`items[${index}][medicine_label]`" x-bind:value="item.medicine_label">
                                <input type="hidden" x-bind:name="`items[${index}][unit_label]`" x-bind:value="item.unit_label">
                                <input type="hidden" x-bind:name="`items[${index}][base_unit]`" x-bind:value="item.base_unit">
                                <input type="hidden" x-bind:name="`items[${index}][conversion_factor]`" x-bind:value="item.conversion_factor">
                                <input type="hidden" x-bind:name="`items[${index}][ordered_quantity]`" x-bind:value="item.ordered_quantity">
                                <input type="hidden" x-bind:name="`items[${index}][ordered_quantity_base]`" x-bind:value="item.ordered_quantity_base">
                                <input type="hidden" x-bind:name="`items[${index}][received_quantity]`" x-bind:value="item.received_quantity">
                                <input type="hidden" x-bind:name="`items[${index}][received_quantity_base]`" x-bind:value="item.received_quantity_base">
                                <input type="hidden" x-bind:name="`items[${index}][outstanding_quantity]`" x-bind:value="item.outstanding_quantity">
                                <input type="hidden" x-bind:name="`items[${index}][outstanding_quantity_base]`" x-bind:value="item.outstanding_quantity_base">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white" x-text="item.medicine_label"></div>
                                        <div class="mt-1 text-xs text-gray-400" x-text="`Ordered ${item.ordered_quantity} ${item.unit_label} | Sudah diterima ${item.received_quantity} ${item.unit_label} | Outstanding ${item.outstanding_quantity} ${item.unit_label}`"></div>
                                        <div class="mt-1 text-xs text-gray-400" x-show="item.base_unit && item.unit_label !== item.base_unit" x-text="`Base stock ${item.outstanding_quantity_base} ${item.base_unit}`"></div>
                                    </div>
                                    <div class="rounded-xl bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-white/[0.03] dark:text-gray-300" x-text="`Subtotal Rp ${money(itemSubtotal(item))}`"></div>
                                </div>
                                <div class="mt-4 grid gap-4 md:grid-cols-4">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Batch number</label>
                                        <input x-model="item.batch_number" x-bind:name="`items[${index}][batch_number]`" type="text" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Expired date</label>
                                        <input x-model="item.expired_at" x-bind:name="`items[${index}][expired_at]`" type="date" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" x-text="`Qty diterima (${item.unit_label || 'unit'})`"></label>
                                        <input x-model="item.quantity_received_now" x-bind:name="`items[${index}][quantity_received]`" type="number" step="0.01" min="0" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                        <div class="mt-1 text-xs text-gray-400" x-text="`Base qty ${baseReceiptQuantity(item).toFixed(2)} ${item.base_unit || ''}`"></div>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300" x-text="`Unit cost per ${item.unit_label || 'unit'}`"></label>
                                        <input x-model="item.unit_cost" x-bind:name="`items[${index}][unit_cost]`" type="number" step="0.01" min="0" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Item notes</label>
                                    <input x-model="item.notes" x-bind:name="`items[${index}][notes]`" type="text" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex items-center justify-between rounded-2xl bg-gray-50 px-5 py-4 text-sm text-gray-600 dark:bg-white/[0.03] dark:text-gray-300">
                    <span>Estimasi total receipt</span>
                    <span class="text-base font-semibold text-gray-900 dark:text-white" x-text="`Rp ${money(receiptTotal())}`"></span>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="receiptModalOpen = false">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="receiptMode === 'create' ? 'Simpan Goods Receipt' : 'Update Goods Receipt'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="cancelModalOpen" maxWidth="2xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Batalkan Goods Receipt</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Goods receipt yang dibatalkan akan melepaskan batch stok yang baru dibuat.</p>
                </div>
                <button type="button" x-on:click="cancelModalOpen = false" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </button>
            </div>
            <form method="POST" x-bind:action="`${updateBase}/${cancelForm.id}/cancel`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="goods-receipt-cancel">
                <input type="hidden" name="entity_id" x-bind:value="cancelForm.id">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
                    <div class="font-medium text-gray-900 dark:text-white" x-text="cancelForm.receipt_no"></div>
                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-300" x-text="cancelForm.purchase_order_no"></div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alasan batal</label>
                    <textarea x-model="cancelForm.cancel_reason" name="cancel_reason" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Catatan tambahan</label>
                    <textarea x-model="cancelForm.notes" name="notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="cancelModalOpen = false">Batal</x-ui.button>
                    <x-ui.button type="submit">Simpan Pembatalan</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection

