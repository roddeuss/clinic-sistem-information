@php
    $defaultBranchId = (string) ($branchOptions->first()?->id ?? '');
    $defaultSupplierId = (string) ($supplierOptions->first()?->id ?? '');
    $defaultMedicineId = (string) ($medicineOptions->first()?->id ?? '');

    $purchaseOrderModalOpen = $errors->any() && in_array(old('form_context'), ['purchase-order-create', 'purchase-order-update'], true);
    $approveModalOpen = $errors->any() && old('form_context') === 'purchase-order-approve';
    $rejectModalOpen = $errors->any() && old('form_context') === 'purchase-order-reject';
    $cancelModalOpen = $errors->any() && old('form_context') === 'purchase-order-cancel';
    $purchaseOrderModalMode = old('form_context') === 'purchase-order-update' ? 'update' : 'create';

    $oldItems = collect(old('items', []))
        ->map(fn ($item) => [
            'id' => $item['id'] ?? null,
            'medicine_id' => (string) ($item['medicine_id'] ?? $defaultMedicineId),
            'medicine_unit_id' => (string) ($item['medicine_unit_id'] ?? ''),
            'quantity_ordered' => $item['quantity_ordered'] ?? '',
            'unit_cost' => $item['unit_cost'] ?? '',
            'notes' => $item['notes'] ?? '',
        ])
        ->values()
        ->all();

    $purchaseOrderModalForm = [
        'id' => old('entity_id'),
        'po_no' => old('po_no', ''),
        'branch_id' => (string) old('branch_id', $defaultBranchId),
        'supplier_id' => (string) old('supplier_id', $defaultSupplierId),
        'order_date' => old('order_date', now()->toDateString()),
        'expected_date' => old('expected_date', ''),
        'notes' => old('notes', ''),
        'items' => $oldItems !== [] ? $oldItems : [[
            'id' => null,
            'medicine_id' => $defaultMedicineId,
            'medicine_unit_id' => '',
            'quantity_ordered' => '',
            'unit_cost' => '',
            'notes' => '',
        ]],
    ];

    $cancelModalForm = [
        'id' => old('entity_id'),
        'po_no' => old('po_no', ''),
        'supplier_label' => old('supplier_label', ''),
        'cancel_reason' => old('cancel_reason', ''),
        'notes' => old('notes', ''),
    ];

    $approveModalForm = [
        'id' => old('entity_id'),
        'po_no' => old('po_no', ''),
        'supplier_label' => old('supplier_label', ''),
        'total_amount' => (float) old('total_amount', 0),
        'approval_notes' => old('approval_notes', ''),
        'threshold_label' => old('threshold_label', 'Rp ' . number_format((float) $approvalThreshold, 0, ',', '.')),
    ];

    $rejectModalForm = [
        'id' => old('entity_id'),
        'po_no' => old('po_no', ''),
        'supplier_label' => old('supplier_label', ''),
        'total_amount' => (float) old('total_amount', 0),
        'rejection_reason' => old('rejection_reason', ''),
        'notes' => old('notes', ''),
    ];

    $medicineChoices = $medicineOptions
        ->map(fn ($medicine) => [
            'id' => (string) $medicine->id,
            'label' => trim($medicine->code . ' - ' . $medicine->name . ($medicine->strength ? ' | ' . $medicine->strength : '')),
            'base_unit' => $medicine->base_unit,
            'uom_options' => $medicine->units
                ->where('is_active', true)
                ->where('allow_purchase', true)
                ->sortBy(fn ($unit) => sprintf('%08d-%s', $unit->sort_order, $unit->conversion_factor))
                ->map(fn ($unit) => [
                    'id' => (string) $unit->id,
                    'label' => $unit->label,
                    'conversion_factor' => (float) $unit->conversion_factor,
                    'is_base' => (bool) $unit->is_base,
                ])
                ->values()
                ->all(),
        ])
        ->values()
        ->all();
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Purchase Orders" />

    <div
        x-data="{
            purchaseOrderModalOpen: @js($purchaseOrderModalOpen),
            approveModalOpen: @js($approveModalOpen),
            rejectModalOpen: @js($rejectModalOpen),
            cancelModalOpen: @js($cancelModalOpen),
            purchaseOrderMode: @js($purchaseOrderModalMode),
            storeAction: @js(route('purchase-orders.store')),
            updateBase: @js(url('/purchase-orders')),
            approveBase: @js(url('/purchase-orders')),
            rejectBase: @js(url('/purchase-orders')),
            cancelBase: @js(url('/purchase-orders')),
            medicines: @js($medicineChoices),
            form: @js($purchaseOrderModalForm),
            approveForm: @js($approveModalForm),
            rejectForm: @js($rejectModalForm),
            cancelForm: @js($cancelModalForm),
            selectedMedicine(item) {
                return this.medicines.find(medicine => medicine.id === item.medicine_id) ?? null;
            },
            purchaseUnits(item) {
                return this.selectedMedicine(item)?.uom_options ?? [];
            },
            defaultPurchaseUnitForMedicine(medicineId) {
                const medicine = this.medicines.find(item => item.id === medicineId);
                return medicine?.uom_options?.[0]?.id ?? '';
            },
            syncItemUnit(item) {
                const units = this.purchaseUnits(item);

                if (units.length === 0) {
                    item.medicine_unit_id = '';
                    return;
                }

                if (!units.some(unit => unit.id === item.medicine_unit_id)) {
                    item.medicine_unit_id = units[0].id;
                }
            },
            selectedUnit(item) {
                return this.purchaseUnits(item).find(unit => unit.id === item.medicine_unit_id) ?? null;
            },
            itemBaseQuantity(item) {
                return Number(item.quantity_ordered || 0) * Number(this.selectedUnit(item)?.conversion_factor || 1);
            },
            emptyItem() {
                const medicineId = @js($defaultMedicineId);

                return {
                    id: null,
                    medicine_id: medicineId,
                    medicine_unit_id: this.defaultPurchaseUnitForMedicine(medicineId),
                    quantity_ordered: '',
                    unit_cost: '',
                    notes: '',
                };
            },
            emptyForm() {
                return {
                    id: null,
                    po_no: '',
                    branch_id: @js($defaultBranchId),
                    supplier_id: @js($defaultSupplierId),
                    order_date: @js(now()->toDateString()),
                    expected_date: '',
                    notes: '',
                    items: [this.emptyItem()],
                };
            },
            normalizeFormItems() {
                this.form.items = (this.form.items || []).map(item => {
                    const nextItem = {
                        id: item.id ?? null,
                        medicine_id: item.medicine_id || @js($defaultMedicineId),
                        medicine_unit_id: item.medicine_unit_id || '',
                        quantity_ordered: item.quantity_ordered ?? '',
                        unit_cost: item.unit_cost ?? '',
                        notes: item.notes ?? '',
                    };

                    this.syncItemUnit(nextItem);

                    return nextItem;
                });
            },
            openCreate() {
                this.purchaseOrderMode = 'create';
                this.form = this.emptyForm();
                this.normalizeFormItems();
                this.purchaseOrderModalOpen = true;
            },
            openEdit(payload) {
                this.purchaseOrderMode = 'update';
                this.form = payload;
                this.normalizeFormItems();
                this.purchaseOrderModalOpen = true;
            },
            addItem() { this.form.items.push(this.emptyItem()); },
            removeItem(index) {
                if (this.form.items.length === 1) {
                    this.form.items = [this.emptyItem()];
                    return;
                }
                this.form.items.splice(index, 1);
            },
            openApprove(payload) { this.approveForm = payload; this.approveModalOpen = true; },
            openReject(payload) { this.rejectForm = payload; this.rejectModalOpen = true; },
            itemTotal(item) { return Number(item.quantity_ordered || 0) * Number(item.unit_cost || 0); },
            orderTotal() { return (this.form.items || []).reduce((carry, item) => carry + this.itemTotal(item), 0); },
            money(value) { return new Intl.NumberFormat('id-ID').format(Number(value || 0)); },
            openCancel(payload) { this.cancelForm = payload; this.cancelModalOpen = true; },
        }"
        @keydown.escape.window="purchaseOrderModalOpen = false; approveModalOpen = false; rejectModalOpen = false; cancelModalOpen = false"
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Purchase Orders</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Dokumen pesanan pembelian per branch dan supplier. Goods receipt akan mengacu ke purchase order ini
                        sebelum batch stok masuk ke inventory.
                    </p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
                    <div class="font-medium text-gray-900 dark:text-white">Approval threshold</div>
                    <div class="mt-1">PO dengan total mulai Rp {{ number_format((float) $approvalThreshold, 0, ',', '.') }} akan masuk status submitted dan menunggu approval admin.</div>
                </div>
                @if ($abilities['create'])
                    <x-ui.button type="button" x-on:click="openCreate()">Tambah Purchase Order</x-ui.button>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('purchase-orders') }}" class="grid gap-4 md:grid-cols-[1.1fr_220px_220px_180px_180px_auto]">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari nomor PO, supplier, atau medicine" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
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
                    <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                    <option value="submitted" @selected($filters['status'] === 'submitted')>Submitted</option>
                    <option value="rejected" @selected($filters['status'] === 'rejected')>Rejected</option>
                    <option value="ordered" @selected($filters['status'] === 'ordered')>Ordered</option>
                    <option value="partial_received" @selected($filters['status'] === 'partial_received')>Partial received</option>
                    <option value="completed" @selected($filters['status'] === 'completed')>Completed</option>
                    <option value="cancelled" @selected($filters['status'] === 'cancelled')>Cancelled</option>
                </select>
                <input type="date" name="date" value="{{ $filters['date'] }}" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <div class="flex gap-3">
                    <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
                    <a href="{{ route('purchase-orders') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">PO list</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $purchaseOrders->firstItem() ?? 0 }} - {{ $purchaseOrders->lastItem() ?? 0 }} dari {{ $purchaseOrders->total() }} purchase order.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">PO</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Supplier</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Progress</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Total</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($purchaseOrders as $purchaseOrder)
                            @php
                                $receivedSummary = $purchaseOrder->items->map(function ($item) {
                                    $received = $item->goodsReceiptItems
                                        ->filter(fn ($receiptItem) => $receiptItem->goodsReceipt?->status !== 'cancelled')
                                        ->sum(fn ($receiptItem) => (float) $receiptItem->quantity_received_base);

                                    return [
                                        'ordered' => (float) $item->quantity_ordered_base,
                                        'received' => (float) $received,
                                    ];
                                });

                                $orderedTotal = $receivedSummary->sum('ordered');
                                $receivedTotal = $receivedSummary->sum('received');

                                $editPayload = [
                                    'id' => $purchaseOrder->id,
                                    'po_no' => $purchaseOrder->po_no,
                                    'branch_id' => (string) $purchaseOrder->branch_id,
                                    'supplier_id' => (string) $purchaseOrder->supplier_id,
                                    'order_date' => $purchaseOrder->order_date?->format('Y-m-d'),
                                    'expected_date' => $purchaseOrder->expected_date?->format('Y-m-d') ?? '',
                                    'notes' => $purchaseOrder->notes ?? '',
                                    'items' => $purchaseOrder->items->map(fn ($item) => [
                                        'id' => $item->id,
                                        'medicine_id' => (string) $item->medicine_id,
                                        'medicine_unit_id' => (string) ($item->medicine_unit_id ?? ''),
                                        'quantity_ordered' => (string) $item->quantity_ordered,
                                        'unit_cost' => (string) $item->unit_cost,
                                        'notes' => $item->notes ?? '',
                                    ])->values()->all(),
                                ];

                                $cancelPayload = [
                                    'id' => $purchaseOrder->id,
                                    'po_no' => $purchaseOrder->po_no,
                                    'supplier_label' => trim(($purchaseOrder->supplier?->code ?? '-') . ' - ' . ($purchaseOrder->supplier?->name ?? '-')),
                                    'cancel_reason' => '',
                                    'notes' => $purchaseOrder->notes ?? '',
                                ];

                                $approvalPayload = [
                                    'id' => $purchaseOrder->id,
                                    'po_no' => $purchaseOrder->po_no,
                                    'supplier_label' => trim(($purchaseOrder->supplier?->code ?? '-') . ' - ' . ($purchaseOrder->supplier?->name ?? '-')),
                                    'total_amount' => (float) $purchaseOrder->total_amount,
                                    'approval_notes' => $purchaseOrder->approval_notes ?? '',
                                    'threshold_label' => 'Rp ' . number_format((float) $approvalThreshold, 0, ',', '.'),
                                ];

                                $rejectPayload = [
                                    'id' => $purchaseOrder->id,
                                    'po_no' => $purchaseOrder->po_no,
                                    'supplier_label' => trim(($purchaseOrder->supplier?->code ?? '-') . ' - ' . ($purchaseOrder->supplier?->name ?? '-')),
                                    'total_amount' => (float) $purchaseOrder->total_amount,
                                    'rejection_reason' => '',
                                    'notes' => $purchaseOrder->notes ?? '',
                                ];

                                $canEdit = in_array($purchaseOrder->status, ['draft', 'rejected'], true) && $purchaseOrder->goodsReceipts->where('status', '!=', 'cancelled')->isEmpty();
                                $canSubmit = in_array($purchaseOrder->status, ['draft', 'rejected'], true);
                                $canApprove = $abilities['approve'] && $purchaseOrder->status === 'submitted';
                                $canReject = $abilities['approve'] && $purchaseOrder->status === 'submitted';
                                $canReceive = in_array($purchaseOrder->status, ['ordered', 'partial_received'], true) && $receivedTotal < $orderedTotal;
                                $canDelete = in_array($purchaseOrder->status, ['draft', 'rejected'], true) && $purchaseOrder->goodsReceipts->where('status', '!=', 'cancelled')->isEmpty();
                                $canCancel = ! in_array($purchaseOrder->status, ['cancelled', 'completed'], true) && $purchaseOrder->goodsReceipts->where('status', '!=', 'cancelled')->isEmpty();
                                $statusClasses = match ($purchaseOrder->status) {
                                    'draft' => 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300',
                                    'submitted' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300',
                                    'rejected' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
                                    'ordered' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300',
                                    'partial_received' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                                    'completed' => 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300',
                                    'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-300',
                                    default => 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300',
                                };
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $purchaseOrder->po_no }}</div>
                                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $purchaseOrder->branch?->code }} - {{ $purchaseOrder->branch?->name }}</div>
                                    <div class="mt-1 text-xs text-gray-400">
                                        Order {{ $purchaseOrder->order_date?->format('d M Y') }}
                                        @if ($purchaseOrder->expected_date)
                                            | ETA {{ $purchaseOrder->expected_date->format('d M Y') }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $purchaseOrder->supplier?->code }} - {{ $purchaseOrder->supplier?->name }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $purchaseOrder->items->count() }} item</div>
                                    @if ($purchaseOrder->notes)
                                        <div class="mt-1 text-xs text-gray-400">{{ $purchaseOrder->notes }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ number_format($receivedTotal, 2) }} / {{ number_format($orderedTotal, 2) }} base qty</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $purchaseOrder->goodsReceipts->where('status', '!=', 'cancelled')->count() }} goods receipt aktif</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>Rp {{ number_format((float) $purchaseOrder->total_amount, 0, ',', '.') }}</div>
                                    @if ($purchaseOrder->approved_at)
                                        <div class="mt-1 text-xs text-gray-400">Approved {{ $purchaseOrder->approved_at->format('d M Y H:i') }}</div>
                                    @elseif ($purchaseOrder->submitted_at)
                                        <div class="mt-1 text-xs text-gray-400">Submitted {{ $purchaseOrder->submitted_at->format('d M Y H:i') }}</div>
                                    @endif
                                    @if ($purchaseOrder->status === 'submitted')
                                        <div class="mt-1 text-xs text-indigo-500 dark:text-indigo-300">Menunggu approval admin</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses }}">
                                        {{ str_replace('_', ' ', ucfirst($purchaseOrder->status)) }}
                                    </span>
                                    @if ($purchaseOrder->rejection_reason)
                                        <div class="mt-2 text-xs text-rose-500 dark:text-rose-300">{{ $purchaseOrder->rejection_reason }}</div>
                                    @elseif ($purchaseOrder->approval_notes)
                                        <div class="mt-2 text-xs text-gray-400">{{ $purchaseOrder->approval_notes }}</div>
                                    @endif
                                    @if ($purchaseOrder->cancel_reason)
                                        <div class="mt-2 text-xs text-gray-400">{{ $purchaseOrder->cancel_reason }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'] && $canEdit)
                                            <button type="button" title="Edit purchase order" data-payload='@json($editPayload)' x-on:click='openEdit(JSON.parse($el.dataset.payload))' class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </button>
                                        @endif
                                        @if ($abilities['submit'] && $canSubmit)
                                            <form method="POST" action="{{ route('purchase-orders.submit', $purchaseOrder) }}">
                                                @csrf
                                                <button type="submit" title="Submit purchase order" class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 text-white transition hover:bg-brand-600">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 12H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M13 6L19 12L13 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                        @if ($canApprove)
                                            <button type="button" title="Approve purchase order" data-payload='@json($approvalPayload)' x-on:click='openApprove(JSON.parse($el.dataset.payload))' class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-emerald-200 text-emerald-600 transition hover:bg-emerald-50 dark:border-emerald-500/20 dark:text-emerald-300 dark:hover:bg-emerald-500/10">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 12L9 17L20 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </button>
                                        @endif
                                        @if ($canReject)
                                            <button type="button" title="Reject purchase order" data-payload='@json($rejectPayload)' x-on:click='openReject(JSON.parse($el.dataset.payload))' class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-rose-200 text-rose-600 transition hover:bg-rose-50 dark:border-rose-500/20 dark:text-rose-300 dark:hover:bg-rose-500/10">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </button>
                                        @endif
                                        @if ($canReceive)
                                            <a href="{{ route('goods-receipts', ['purchase_order' => $purchaseOrder->id]) }}" title="Buat goods receipt" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-blue-200 text-blue-600 transition hover:bg-blue-50 dark:border-blue-500/20 dark:text-blue-300 dark:hover:bg-blue-500/10">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 7H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 12L11 16L19 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 7L6 18.5C6.06 19.33 6.75 19.96 7.58 19.96H16.42C17.25 19.96 17.94 19.33 18 18.5L19 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </a>
                                        @endif
                                        @if ($abilities['cancel'] && $canCancel)
                                            <button type="button" title="Batalkan purchase order" data-payload='@json($cancelPayload)' x-on:click='openCancel(JSON.parse($el.dataset.payload))' class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-amber-200 text-amber-600 transition hover:bg-amber-50 dark:border-amber-500/20 dark:text-amber-300 dark:hover:bg-amber-500/10">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 8V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12 16H12.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10.29 3.86L1.82 18A2 2 0 0 0 3.53 21H20.47A2 2 0 0 0 22.18 18L13.71 3.86A2 2 0 0 0 10.29 3.86Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
                                            </button>
                                        @endif
                                        @if ($abilities['delete'] && $canDelete)
                                            <form method="POST" action="{{ route('purchase-orders.delete', $purchaseOrder) }}" onsubmit="return confirm('Hapus purchase order draft ini?')">
                                                @csrf
                                                <button type="submit" title="Hapus purchase order" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-red-200 text-red-600 transition hover:bg-red-50 dark:border-red-500/20 dark:text-red-300 dark:hover:bg-red-500/10">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 7L8 18.5C8.06 19.33 8.75 19.96 9.58 19.96H14.42C15.25 19.96 15.94 19.33 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 7V5.75C9 5.06 9.56 4.5 10.25 4.5H13.75C14.44 4.5 15 5.06 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada purchase order.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $purchaseOrders->links() }}</div>
        </section>

        <x-ui.modal show="purchaseOrderModalOpen" maxWidth="5xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="purchaseOrderMode === 'create' ? 'Tambah Purchase Order' : 'Update Purchase Order'"></h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Isi header dan daftar item yang akan dipesan ke supplier.</p>
                </div>
                <button type="button" x-on:click="purchaseOrderModalOpen = false" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </button>
            </div>
            <form method="POST" x-bind:action="purchaseOrderMode === 'create' ? storeAction : `${updateBase}/${form.id}`" class="space-y-6 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="purchaseOrderMode === 'create' ? 'purchase-order-create' : 'purchase-order-update'">
                <input type="hidden" name="entity_id" x-bind:value="form.id">
                <input type="hidden" name="po_no" x-bind:value="form.po_no">
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Branch</label>
                        <select x-model="form.branch_id" name="branch_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($branchOptions as $branchOption)
                                <option value="{{ $branchOption->id }}">{{ $branchOption->code }} - {{ $branchOption->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Supplier</label>
                        <select x-model="form.supplier_id" name="supplier_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($supplierOptions as $supplierOption)
                                <option value="{{ $supplierOption->id }}">{{ $supplierOption->code }} - {{ $supplierOption->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Order date</label>
                        <input x-model="form.order_date" type="date" name="order_date" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Expected date</label>
                        <input x-model="form.expected_date" type="date" name="expected_date" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                    <textarea x-model="form.notes" name="notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                </div>
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white">PO items</h4>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Satu medicine hanya boleh satu baris per purchase order.</p>
                        </div>
                        <button type="button" x-on:click="addItem()" class="inline-flex h-10 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">Tambah Item</button>
                    </div>
                    <div class="space-y-4 p-5">
                        <template x-for="(item, index) in form.items" :key="index">
                            <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                                <input type="hidden" x-bind:name="`items[${index}][id]`" x-bind:value="item.id">
                                <div class="grid gap-4 md:grid-cols-[1.3fr_0.8fr_0.8fr_0.9fr_auto]">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Medicine</label>
                                        <select x-model="item.medicine_id" x-bind:name="`items[${index}][medicine_id]`" x-on:change="syncItemUnit(item)" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                            <template x-for="medicine in medicines" :key="medicine.id">
                                                <option x-bind:value="medicine.id" x-text="medicine.label"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">UOM</label>
                                        <select x-model="item.medicine_unit_id" x-bind:name="`items[${index}][medicine_unit_id]`" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                            <template x-for="unit in purchaseUnits(item)" :key="`${item.medicine_id}-${unit.id}`">
                                                <option x-bind:value="unit.id" x-text="unit.label"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Qty ordered</label>
                                        <input x-model="item.quantity_ordered" x-bind:name="`items[${index}][quantity_ordered]`" type="number" step="0.01" min="0" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Unit cost</label>
                                        <input x-model="item.unit_cost" x-bind:name="`items[${index}][unit_cost]`" type="number" step="0.01" min="0" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                    </div>
                                    <div class="flex items-end justify-end">
                                        <button type="button" x-on:click="removeItem(index)" class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-red-200 text-red-600 transition hover:bg-red-50 dark:border-red-500/20 dark:text-red-300 dark:hover:bg-red-500/10">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 7L8 18.5C8.06 19.33 8.75 19.96 9.58 19.96H14.42C15.25 19.96 15.94 19.33 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 7V5.75C9 5.06 9.56 4.5 10.25 4.5H13.75C14.44 4.5 15 5.06 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-4 md:grid-cols-[1fr_auto]">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Item notes</label>
                                        <input x-model="item.notes" x-bind:name="`items[${index}][notes]`" type="text" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                    </div>
                                    <div class="flex items-end">
                                        <div class="rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:bg-white/[0.03] dark:text-gray-300">
                                            <div>Total <span class="font-semibold text-gray-900 dark:text-white" x-text="`Rp ${money(itemTotal(item))}`"></span></div>
                                            <div class="mt-1 text-xs text-gray-400" x-show="selectedUnit(item)" x-text="selectedUnit(item) ? `${Number(item.quantity_ordered || 0)} ${selectedUnit(item).label} = ${itemBaseQuantity(item).toFixed(2)} ${selectedMedicine(item)?.base_unit || ''}` : ''"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="flex items-center justify-between rounded-2xl bg-gray-50 px-5 py-4 text-sm text-gray-600 dark:bg-white/[0.03] dark:text-gray-300">
                    <span>Estimasi total PO</span>
                    <span class="text-base font-semibold text-gray-900 dark:text-white" x-text="`Rp ${money(orderTotal())}`"></span>
                </div>
                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="purchaseOrderModalOpen = false">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="purchaseOrderMode === 'create' ? 'Simpan Purchase Order' : 'Update Purchase Order'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="approveModalOpen" maxWidth="2xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Approve Purchase Order</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">PO ini melewati threshold approval dan perlu persetujuan admin sebelum bisa diterima barangnya.</p>
                </div>
                <button type="button" x-on:click="approveModalOpen = false" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </button>
            </div>
            <form method="POST" x-bind:action="`${approveBase}/${approveForm.id}/approve`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="purchase-order-approve">
                <input type="hidden" name="entity_id" x-bind:value="approveForm.id">
                <input type="hidden" name="po_no" x-bind:value="approveForm.po_no">
                <input type="hidden" name="supplier_label" x-bind:value="approveForm.supplier_label">
                <input type="hidden" name="total_amount" x-bind:value="approveForm.total_amount">
                <input type="hidden" name="threshold_label" x-bind:value="approveForm.threshold_label">
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                    <div class="font-medium text-gray-900 dark:text-white" x-text="approveForm.po_no"></div>
                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-300" x-text="approveForm.supplier_label"></div>
                    <div class="mt-2 text-sm text-emerald-700 dark:text-emerald-300" x-text="`Total Rp ${money(approveForm.total_amount)} | Threshold ${approveForm.threshold_label}`"></div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Catatan approval</label>
                    <textarea x-model="approveForm.approval_notes" name="approval_notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="approveModalOpen = false">Batal</x-ui.button>
                    <x-ui.button type="submit">Approve Purchase Order</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="rejectModalOpen" maxWidth="2xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Reject Purchase Order</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Isi alasan reject supaya pembuat PO bisa revisi dan submit ulang.</p>
                </div>
                <button type="button" x-on:click="rejectModalOpen = false" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </button>
            </div>
            <form method="POST" x-bind:action="`${rejectBase}/${rejectForm.id}/reject`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="purchase-order-reject">
                <input type="hidden" name="entity_id" x-bind:value="rejectForm.id">
                <input type="hidden" name="po_no" x-bind:value="rejectForm.po_no">
                <input type="hidden" name="supplier_label" x-bind:value="rejectForm.supplier_label">
                <input type="hidden" name="total_amount" x-bind:value="rejectForm.total_amount">
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-500/20 dark:bg-rose-500/10">
                    <div class="font-medium text-gray-900 dark:text-white" x-text="rejectForm.po_no"></div>
                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-300" x-text="rejectForm.supplier_label"></div>
                    <div class="mt-2 text-sm text-rose-700 dark:text-rose-300" x-text="`Total Rp ${money(rejectForm.total_amount)}`"></div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alasan reject</label>
                    <textarea x-model="rejectForm.rejection_reason" name="rejection_reason" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Catatan tambahan</label>
                    <textarea x-model="rejectForm.notes" name="notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="rejectModalOpen = false">Batal</x-ui.button>
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-rose-600 px-5 py-3.5 text-sm font-medium text-white transition hover:bg-rose-700">Simpan Reject</button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="cancelModalOpen" maxWidth="2xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Batalkan Purchase Order</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pembatalan hanya bisa dilakukan sebelum ada goods receipt aktif.</p>
                </div>
                <button type="button" x-on:click="cancelModalOpen = false" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </button>
            </div>
            <form method="POST" x-bind:action="`${cancelBase}/${cancelForm.id}/cancel`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="purchase-order-cancel">
                <input type="hidden" name="entity_id" x-bind:value="cancelForm.id">
                <input type="hidden" name="po_no" x-bind:value="cancelForm.po_no">
                <input type="hidden" name="supplier_label" x-bind:value="cancelForm.supplier_label">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
                    <div class="font-medium text-gray-900 dark:text-white" x-text="cancelForm.po_no"></div>
                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-300" x-text="cancelForm.supplier_label"></div>
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

