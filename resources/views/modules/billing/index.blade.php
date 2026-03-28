@php
    $defaultPaymentMethodId = (string) ($paymentMethodOptions->first()?->id ?? '');
    $payModalOpen = $errors->any() && old('form_context') === 'invoice-pay';
    $tempoModalOpen = $errors->any() && old('form_context') === 'invoice-tempo';
    $voidModalOpen = $errors->any() && old('form_context') === 'invoice-void';
    $payModalForm = [
        'id' => old('entity_id'),
        'payment_method_id' => old('payment_method_id', $defaultPaymentMethodId),
        'amount' => old('amount', 0),
        'payment_reference' => old('payment_reference', ''),
        'notes' => old('notes', ''),
    ];
    $voidModalForm = [
        'id' => old('entity_id'),
        'invoice_no' => old('invoice_no', ''),
        'patient' => old('patient', ''),
        'total_amount' => old('total_amount', 0),
        'void_reason' => old('void_reason', ''),
        'notes' => old('notes', ''),
    ];
    $tempoModalForm = [
        'id' => old('entity_id'),
        'invoice_no' => old('invoice_no', ''),
        'patient' => old('patient', ''),
        'total_amount' => old('total_amount', 0),
        'due_date' => old('due_date', $defaultReceivableDueDate),
        'payer_type' => old('payer_type', 'self_pay'),
        'payer_name' => old('payer_name', ''),
        'payer_contact_person' => old('payer_contact_person', ''),
        'payer_phone' => old('payer_phone', ''),
        'notes' => old('notes', ''),
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Sales Invoices" />

    <div
        x-data="{
            detailModalOpen: false,
            payModalOpen: @js($payModalOpen),
            tempoModalOpen: @js($tempoModalOpen),
            voidModalOpen: @js($voidModalOpen),
            payBase: @js(url('/billing')),
            detail: null,
            payForm: @js($payModalForm),
            tempoForm: @js($tempoModalForm),
            voidForm: @js($voidModalForm),
            defaultPaymentMethodId: @js($defaultPaymentMethodId),
            money(value) { return new Intl.NumberFormat('id-ID').format(Number(value || 0)); },
            openDetail(payload) { this.detail = payload; this.detailModalOpen = true; },
            openPay(payload) {
                this.detail = payload;
                this.payForm = { id: payload.id, payment_method_id: this.defaultPaymentMethodId, amount: payload.outstanding_amount, payment_reference: '', notes: '' };
                this.payModalOpen = true;
            },
            openTempo(payload) {
                this.detail = payload;
                this.tempoForm = { id: payload.id, invoice_no: payload.invoice_no, patient: payload.patient, total_amount: payload.total_amount, due_date: payload.receivable_due_date || @js($defaultReceivableDueDate), payer_type: payload.payer_type || 'self_pay', payer_name: payload.payer_name || '', payer_contact_person: payload.payer_contact_person || '', payer_phone: payload.payer_phone || '', notes: payload.notes || '' };
                this.tempoModalOpen = true;
            },
            openVoid(payload) {
                this.detail = payload;
                this.voidForm = { id: payload.id, invoice_no: payload.invoice_no, patient: payload.patient, total_amount: payload.total_amount, void_reason: '', notes: '' };
                this.voidModalOpen = true;
            },
        }"
        @keydown.escape.window="detailModalOpen = false; payModalOpen = false; tempoModalOpen = false; voidModalOpen = false"
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Sales Invoices</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">Invoice dibuat otomatis per visit dari consultation, procedure completed, diagnostics yang billable, medical service, dan obat in-house yang benar-benar dispensed. Untuk receivable, sistem sekarang mendukung cicilan dan payer perusahaan.</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
                    <div class="font-medium text-gray-900 dark:text-white">Direct pay atau cicilan receivable</div>
                    <div class="mt-1">Invoice A4, receipt thermal, receivable self-pay, dan receivable perusahaan tersedia dari action kasir.</div>
                </div>
            </div>
        </section>

        @if ($activeShift)
            <section class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-6 shadow-theme-sm dark:border-emerald-500/20 dark:bg-emerald-500/10">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-600 dark:text-emerald-300">Active Cashier Shift</div>
                        <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $activeShift->shift_code }}</div>
                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $activeShift->branch?->code }} | {{ $activeShift->counter?->code }} - {{ $activeShift->counter?->name }} | {{ $activeShift->user?->name }}</div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Invoice hanya bisa dibayar bila branch invoice sama dengan branch shift aktif ini.</div>
                    </div>
                    <a href="{{ route('cashier-shifts') }}" class="inline-flex h-11 items-center rounded-xl border border-emerald-200 bg-white px-4 text-sm font-medium text-emerald-700 shadow-theme-xs transition hover:bg-emerald-50 dark:border-emerald-500/30 dark:bg-gray-900 dark:text-emerald-300 dark:hover:bg-white/[0.03]">Kelola Cashier Shift</a>
                </div>
            </section>
        @else
            <section class="rounded-2xl border border-amber-200 bg-amber-50/80 p-6 shadow-theme-sm dark:border-amber-500/20 dark:bg-amber-500/10">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-600 dark:text-amber-300">Cashier Shift Required</div>
                        <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">Belum ada shift aktif untuk user ini</div>
                        <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">Buka cashier shift terlebih dahulu sebelum menerima pembayaran invoice.</div>
                    </div>
                    <a href="{{ route('cashier-shifts') }}" class="inline-flex h-11 items-center rounded-xl border border-amber-200 bg-white px-4 text-sm font-medium text-amber-700 shadow-theme-xs transition hover:bg-amber-50 dark:border-amber-500/30 dark:bg-gray-900 dark:text-amber-300 dark:hover:bg-white/[0.03]">Buka Cashier Shift</a>
                </div>
            </section>
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('billing') }}" class="grid gap-4 md:grid-cols-[1.2fr_220px_180px_180px_auto]">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari invoice, patient, RM, atau HP" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <select name="branch" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Semua branch</option>
                    @foreach ($branchOptions as $branchOption)
                        <option value="{{ $branchOption->id }}" @selected($filters['branch'] === (string) $branchOption->id)>{{ $branchOption->code }} - {{ $branchOption->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Semua status</option>
                    <option value="unpaid" @selected($filters['status'] === 'unpaid')>Unpaid</option>
                    <option value="partial_paid" @selected($filters['status'] === 'partial_paid')>Partial paid</option>
                    <option value="paid" @selected($filters['status'] === 'paid')>Paid</option>
                    <option value="voided" @selected($filters['status'] === 'voided')>Voided</option>
                </select>
                <input type="date" name="date" value="{{ $filters['date'] }}" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <div class="flex gap-3">
                    <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
                    <a href="{{ route('billing') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Invoice list</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $invoices->firstItem() ?? 0 }} - {{ $invoices->lastItem() ?? 0 }} dari {{ $invoices->total() }} invoice.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Invoice</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Patient</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Settlement</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Total</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($invoices as $invoice)
                            @php
                                $invoicePayload = [
                                    'id' => $invoice->id,
                                    'branch_id' => $invoice->branch_id,
                                    'invoice_no' => $invoice->invoice_no,
                                    'status' => $invoice->status,
                                    'branch' => $invoice->branch?->code,
                                    'patient' => $invoice->patient?->full_name,
                                    'medical_record_no' => $invoice->patientBranchRecord?->medical_record_no,
                                    'phone' => $invoice->patient?->phone,
                                    'section' => $invoice->visitRegistration?->section?->name,
                                    'created_at' => $invoice->created_at?->format('d M Y H:i'),
                                    'issued_at' => $invoice->issued_at?->format('d M Y H:i'),
                                    'paid_at' => $invoice->paid_at?->format('d M Y H:i'),
                                    'paid_by' => $invoice->paidBy?->name,
                                    'payment_method' => $invoice->paymentMethod?->name,
                                    'payment_reference' => $invoice->payment_reference,
                                    'cashier_shift' => $invoice->cashierShift?->shift_code,
                                    'cashier_counter' => $invoice->cashierShift?->counter?->code,
                                    'payer_type' => $invoice->payer_type,
                                    'payer_name' => $invoice->payer_name,
                                    'payer_contact_person' => $invoice->payer_meta['company_contact_person'] ?? null,
                                    'payer_phone' => $invoice->payer_meta['company_phone'] ?? null,
                                    'receivable_status' => $invoice->receivable?->status,
                                    'receivable_due_date' => $invoice->receivable?->due_date?->toDateString(),
                                    'receivable_due_label' => $invoice->receivable?->due_date?->format('d M Y'),
                                    'printed_at' => $invoice->printed_at?->format('d M Y H:i'),
                                    'void_reason' => $invoice->void_reason,
                                    'subtotal' => (float) $invoice->subtotal,
                                    'discount_amount' => (float) $invoice->discount_amount,
                                    'total_amount' => (float) $invoice->total_amount,
                                    'paid_amount' => (float) $invoice->paid_amount,
                                    'outstanding_amount' => max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount),
                                    'payment_count' => $invoice->payments->count(),
                                    'notes' => $invoice->notes,
                                    'payments' => $invoice->payments->map(fn ($payment) => [
                                        'amount' => (float) $payment->amount,
                                        'payment_method' => $payment->paymentMethod?->name ?? '-',
                                        'payment_reference' => $payment->payment_reference,
                                        'payment_date' => $payment->payment_date?->format('d M Y H:i'),
                                        'cashier_shift' => $payment->cashierShift?->shift_code,
                                        'cashier_counter' => $payment->cashierShift?->counter?->code,
                                        'paid_by' => $payment->paidBy?->name,
                                        'notes' => $payment->notes,
                                    ])->values()->all(),
                                    'items' => $invoice->items->map(fn ($item) => [
                                        'item_type' => $item->item_type,
                                        'description' => $item->description,
                                        'quantity' => (float) $item->quantity,
                                        'unit_price' => (float) $item->unit_price,
                                        'subtotal' => (float) $item->subtotal,
                                    ])->values()->all(),
                                ];
                                $statusClasses = match ($invoice->status) {
                                    'paid' => 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300',
                                    'partial_paid' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300',
                                    'voided' => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-300',
                                    default => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                                };
                                $canPayInvoice = $abilities['pay']
                                    && in_array($invoice->status, ['unpaid', 'partial_paid'], true)
                                    && $activeShift
                                    && ((float) $invoice->total_amount - (float) $invoice->paid_amount) > 0
                                    && (int) $activeShift->branch_id === (int) $invoice->branch_id;
                                $canTempoInvoice = $abilities['tempo']
                                    && in_array($invoice->status, ['unpaid', 'partial_paid'], true)
                                    && ((float) $invoice->total_amount - (float) $invoice->paid_amount) > 0
                                    && ! in_array($invoice->receivable?->status, ['open', 'overdue'], true);
                                $payDisabledReason = ! $activeShift
                                    ? 'Buka cashier shift terlebih dahulu'
                                    : ((int) $activeShift->branch_id !== (int) $invoice->branch_id
                                        ? 'Branch invoice berbeda dengan shift aktif'
                                        : 'Invoice sudah tidak memiliki outstanding');
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $invoice->invoice_no }}</div>
                                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $invoice->branch?->code }} | {{ $invoice->visitRegistration?->section?->name ?? '-' }}</div>
                                    <div class="mt-1 text-xs text-gray-400">Issued {{ $invoice->issued_at?->format('d M Y H:i') ?? $invoice->created_at?->format('d M Y H:i') }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $invoice->patient?->full_name }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $invoice->patientBranchRecord?->medical_record_no }} | {{ $invoice->patient?->phone }}</div>
                                    <div class="mt-2 space-y-1 text-xs text-gray-400">
                                        @foreach ($invoice->items->take(2) as $item)
                                            <div>{{ $item->description }}</div>
                                        @endforeach
                                        @if ($invoice->items->count() > 2)
                                            <div>+{{ $invoice->items->count() - 2 }} item lain</div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $invoice->paymentMethod?->name ?? ($invoice->payments->isNotEmpty() ? 'Installment recorded' : ($invoice->receivable ? 'Receivable' : '-')) }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $invoice->cashierShift?->shift_code ?? ($invoice->receivable ? 'Tempo aktif' : 'Belum dibayar') }}</div>
                                    <div class="mt-1 text-xs text-gray-400">
                                        @if ($invoice->payment_reference)
                                            Ref {{ $invoice->payment_reference }}
                                        @elseif ($invoice->payments->isNotEmpty())
                                            {{ $invoice->payments->count() }} pembayaran | Outstanding Rp {{ number_format(max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount), 0, ',', '.') }}
                                        @elseif ($invoice->receivable && in_array($invoice->receivable->status, ['open', 'overdue'], true))
                                            Tempo sampai {{ $invoice->receivable->due_date?->format('d M Y') }}
                                        @elseif ($invoice->paid_at)
                                            Dibayar {{ $invoice->paid_at?->format('d M Y H:i') }}
                                        @else
                                            Menunggu pembayaran
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div class="font-medium text-gray-900 dark:text-white">Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</div>
                                    <div class="mt-1 text-xs text-gray-400">Paid Rp {{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}</div>
                                    <div class="mt-1 text-xs text-gray-400">Outstanding Rp {{ number_format(max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount), 0, ',', '.') }}</div>
                                    <div class="mt-1 text-xs text-gray-400">Subtotal Rp {{ number_format((float) $invoice->subtotal, 0, ',', '.') }}</div>
                                    @if ((float) $invoice->discount_amount > 0)
                                        <div class="mt-1 text-xs text-gray-400">Diskon Rp {{ number_format((float) $invoice->discount_amount, 0, ',', '.') }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses }}">{{ str($invoice->status)->headline() }}</span>
                                        @if ($invoice->printed_at)
                                            <div class="mt-2 text-xs text-gray-400">Printed {{ $invoice->printed_at->format('d M Y H:i') }}</div>
                                        @endif
                                        @if ($invoice->receivable && in_array($invoice->receivable->status, ['open', 'overdue'], true))
                                            <div class="mt-2 text-xs text-amber-500 dark:text-amber-300">Receivable {{ str($invoice->receivable->status)->headline() }}</div>
                                        @endif
                                        @if ($invoice->payer_type === 'corporate')
                                            <div class="mt-2 text-xs text-indigo-500 dark:text-indigo-300">Payer perusahaan{{ $invoice->payer_name ? ': ' . $invoice->payer_name : '' }}</div>
                                        @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        <x-ui.icon-button title="Lihat detail" data-payload='@json($invoicePayload)' x-on:click='openDetail(JSON.parse($el.dataset.payload))'>
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 12C4.8 8.4 8.1 6.5 12 6.5C15.9 6.5 19.2 8.4 21 12C19.2 15.6 15.9 17.5 12 17.5C8.1 17.5 4.8 15.6 3 12Z" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.5"/></svg>
                                        </x-ui.icon-button>
                                        @if ($abilities['refresh'])
                                            <form method="POST" action="{{ route('billing.refresh', $invoice) }}">
                                                @csrf
                                                <x-ui.icon-button type="submit" title="Refresh invoice">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 12A8 8 0 1 1 17.66 6.34" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M20 4V9H15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                </x-ui.icon-button>
                                            </form>
                                        @endif
                                        @if ($abilities['print'])
                                            <a href="{{ route('billing.print', $invoice) }}" target="_blank" rel="noopener" title="Print invoice A4" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M7 8V4.75C7 4.33579 7.33579 4 7.75 4H16.25C16.6642 4 17 4.33579 17 4.75V8" stroke="currentColor" stroke-width="1.5"/><path d="M6.5 18.5H17.5C18.3284 18.5 19 17.8284 19 17V12C19 11.1716 18.3284 10.5 17.5 10.5H6.5C5.67157 10.5 5 11.1716 5 12V17C5 17.8284 5.67157 18.5 6.5 18.5Z" stroke="currentColor" stroke-width="1.5"/><path d="M8 14.5H16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M8 17H13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </a>
                                        @endif
                                        @if ($abilities['receipt'] && $invoice->status === 'paid')
                                            <a href="{{ route('billing.receipt', $invoice) }}" target="_blank" rel="noopener" title="Print receipt thermal" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-emerald-200 bg-white text-emerald-600 transition hover:bg-emerald-50 hover:text-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-emerald-500/20 dark:bg-gray-900 dark:text-emerald-300 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-200">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M7.75 4.75H16.25C16.6642 4.75 17 5.08579 17 5.5V8H7V5.5C7 5.08579 7.33579 4.75 7.75 4.75Z" stroke="currentColor" stroke-width="1.5"/><path d="M6 9.5H18C19.1046 9.5 20 10.3954 20 11.5V16C20 17.1046 19.1046 18 18 18H6C4.89543 18 4 17.1046 4 16V11.5C4 10.3954 4.89543 9.5 6 9.5Z" stroke="currentColor" stroke-width="1.5"/><path d="M8 13H16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M8 16H13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </a>
                                        @endif
                                        @if (in_array($invoice->status, ['unpaid', 'partial_paid'], true))
                                            @if ($canTempoInvoice)
                                                <x-ui.icon-button title="Buka tempo receivable" data-payload='@json($invoicePayload)' x-on:click='openTempo(JSON.parse($el.dataset.payload))'>
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M8 2V5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M16 2V5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M3.5 9.5H20.5" stroke="currentColor" stroke-width="1.5"/><rect x="3.5" y="4.5" width="17" height="16" rx="2.5" stroke="currentColor" stroke-width="1.5"/><path d="M12 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 13.5H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                                </x-ui.icon-button>
                                            @endif
                                            @if ($canPayInvoice)
                                                <x-ui.icon-button variant="primary" title="Catat pembayaran" data-payload='@json($invoicePayload)' x-on:click='openPay(JSON.parse($el.dataset.payload))'>
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="3.75" y="6.75" width="16.5" height="10.5" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M3.75 10.5H20.25" stroke="currentColor" stroke-width="1.5"/></svg>
                                                </x-ui.icon-button>
                                            @else
                                                <button type="button" disabled title="{{ $payDisabledReason }}" class="inline-flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-lg border border-gray-200 bg-gray-100 text-gray-400 opacity-70 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-500">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="3.75" y="6.75" width="16.5" height="10.5" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M3.75 10.5H20.25" stroke="currentColor" stroke-width="1.5"/></svg>
                                                </button>
                                            @endif
                                        @endif
                                        @if ($abilities['void'] && $invoice->status !== 'voided')
                                            <x-ui.icon-button variant="danger" title="{{ $invoice->status === 'paid' ? 'Void invoice' : 'Cancel invoice' }}" data-payload='@json($invoicePayload)' x-on:click='openVoid(JSON.parse($el.dataset.payload))'>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                            </x-ui.icon-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada invoice.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $invoices->links() }}</div>
        </section>

        <x-ui.modal show="detailModalOpen" maxWidth="4xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Invoice detail</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ringkasan item, payment detail, dan status settlement.</p>
                </div>
                <x-ui.icon-button title="Tutup modal" x-on:click="detailModalOpen = false">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </x-ui.icon-button>
            </div>
            <div class="space-y-5 p-6" x-show="detail">
                <div class="grid gap-5 md:grid-cols-3">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Invoice</div><div class="mt-2 font-semibold text-gray-900 dark:text-white" x-text="detail?.invoice_no"></div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="`${detail?.branch ?? '-'} | ${detail?.status ?? '-'}`"></div><div class="mt-1 text-xs text-gray-400" x-text="`Issued ${detail?.issued_at ?? detail?.created_at ?? '-'}`"></div></div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Patient</div><div class="mt-2 font-semibold text-gray-900 dark:text-white" x-text="detail?.patient"></div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="`${detail?.medical_record_no ?? '-'} | ${detail?.phone ?? '-'}`"></div><div class="mt-1 text-xs text-gray-400" x-text="detail?.section ?? '-'"></div></div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Settlement</div><div class="mt-2 font-semibold text-gray-900 dark:text-white" x-text="detail?.payment_method || (detail?.payments?.length ? 'Installment payments' : 'Belum dibayar')"></div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="detail?.cashier_shift ? `${detail.cashier_shift} | ${detail.cashier_counter ?? '-'}` : 'Belum ada cashier shift settlement'"></div><div class="mt-1 text-xs text-gray-400" x-text="detail?.paid_at ? `Paid ${detail.paid_at}${detail?.paid_by ? ' by ' + detail.paid_by : ''}` : (detail?.payments?.length ? `${detail.payments.length} pembayaran tercatat` : 'Belum ada payment timestamp')"></div></div>
                </div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Payer</div><div class="mt-2" x-text="detail?.payer_type === 'corporate' ? `Perusahaan: ${detail?.payer_name ?? '-'}` : 'Self-pay'"></div><div class="mt-1 text-xs text-gray-400" x-text="detail?.payer_contact_person ? `PIC ${detail.payer_contact_person}` : (detail?.payer_phone ? detail.payer_phone : '')"></div></div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Status Notes</div><div class="mt-2" x-text="detail?.void_reason || detail?.notes || '-'"></div></div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Subtotal</div><div class="mt-2 font-medium text-gray-900 dark:text-white" x-text="`Rp ${money(detail?.subtotal)}`"></div></div>
                        <div><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Paid</div><div class="mt-2 font-medium text-gray-900 dark:text-white" x-text="`Rp ${money(detail?.paid_amount)}`"></div></div>
                        <div><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Outstanding</div><div class="mt-2 font-medium text-gray-900 dark:text-white" x-text="`Rp ${money(detail?.outstanding_amount)}`"></div></div>
                    </div>
                </div>
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Type</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Description</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Qty</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Price</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Subtotal</th></tr></thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <template x-for="item in detail?.items ?? []" :key="`${item.item_type}-${item.description}`">
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" x-text="item.item_type"></td>
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white" x-text="item.description"></td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" x-text="item.quantity"></td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" x-text="`Rp ${money(item.unit_price)}`"></td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" x-text="`Rp ${money(item.subtotal)}`"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800" x-show="detail?.payments?.length">
                    <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-900 dark:border-gray-800 dark:text-white">Riwayat pembayaran</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Tanggal</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Method</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Amount</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Shift</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">By</th></tr></thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <template x-for="payment in detail?.payments ?? []" :key="`${payment.payment_date}-${payment.amount}`">
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" x-text="payment.payment_date || '-'"></td>
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white"><div x-text="payment.payment_method || '-'"></div><div class="mt-1 text-xs text-gray-400" x-text="payment.payment_reference || '-'"></div></td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" x-text="`Rp ${money(payment.amount)}`"></td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" x-text="payment.cashier_shift ? `${payment.cashier_shift} | ${payment.cashier_counter ?? '-'}` : '-'"></td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" x-text="payment.paid_by || '-'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="flex flex-col items-end gap-1 text-sm text-gray-500 dark:text-gray-400">
                    <div x-text="`Discount Rp ${money(detail?.discount_amount)}`"></div>
                    <div class="text-base font-semibold text-gray-900 dark:text-white" x-text="`Total Rp ${money(detail?.total_amount)}`"></div>
                    <div x-show="detail?.printed_at" class="text-xs text-gray-400" x-text="`Printed ${detail?.printed_at}`"></div>
                </div>
            </div>
        </x-ui.modal>

        <x-ui.modal show="payModalOpen" maxWidth="2xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white">Catat pembayaran invoice</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pembayaran akan menautkan invoice ke cashier shift aktif. Untuk receivable, nominal boleh dicicil sampai outstanding lunas.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="payModalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div>
            <form method="POST" x-bind:action="`${payBase}/${payForm.id}/pay`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="invoice-pay">
                <input type="hidden" name="entity_id" x-bind:value="payForm.id">
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="font-medium text-gray-900 dark:text-white" x-text="detail?.invoice_no"></div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="detail?.patient"></div><div class="mt-2 text-base font-semibold text-gray-900 dark:text-white" x-text="`Total Rp ${money(detail?.total_amount)}`"></div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="`Outstanding Rp ${money(detail?.outstanding_amount)}`"></div><div class="mt-2 text-xs text-gray-400">@if ($activeShift)Shift aktif: {{ $activeShift->shift_code }} | {{ $activeShift->branch?->code }} | {{ $activeShift->counter?->code }} - {{ $activeShift->counter?->name }}@else Shift aktif belum tersedia.@endif</div></div>
                <div class="grid gap-5 md:grid-cols-3">
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Payment method</label><select x-model="payForm.payment_method_id" name="payment_method_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Pilih payment method</option>@foreach ($paymentMethodOptions as $paymentMethodOption)<option value="{{ $paymentMethodOption->id }}">{{ $paymentMethodOption->name }} ({{ $paymentMethodOption->code }})</option>@endforeach</select></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nominal bayar</label><input x-model="payForm.amount" type="number" step="0.01" min="0.01" name="amount" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Payment reference</label><input x-model="payForm.payment_reference" type="text" name="payment_reference" placeholder="Opsional, mis. no. transfer / QR ref" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                </div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Catatan pembayaran</label><textarea x-model="payForm.notes" name="notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                <div class="flex justify-end gap-3"><x-ui.button type="button" variant="outline" x-on:click="payModalOpen = false">Batal</x-ui.button><button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-3.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:bg-brand-300" @disabled(! $activeShift || $paymentMethodOptions->isEmpty()) x-text="Number(payForm.amount || 0) >= Number(detail?.outstanding_amount || 0) ? 'Tandai Paid' : 'Catat Cicilan'"></button></div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="tempoModalOpen" maxWidth="2xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white">Buka receivable tempo</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Invoice akan tetap unpaid, tetapi masuk daftar receivable dengan jatuh tempo yang bisa diperpanjang nanti.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="tempoModalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div>
            <form method="POST" x-bind:action="`${payBase}/${tempoForm.id}/tempo`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="invoice-tempo">
                <input type="hidden" name="entity_id" x-bind:value="tempoForm.id">
                <input type="hidden" name="invoice_no" x-bind:value="tempoForm.invoice_no">
                <input type="hidden" name="patient" x-bind:value="tempoForm.patient">
                <input type="hidden" name="total_amount" x-bind:value="tempoForm.total_amount">
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="font-medium text-gray-900 dark:text-white" x-text="tempoForm.invoice_no"></div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="tempoForm.patient"></div><div class="mt-2 text-base font-semibold text-gray-900 dark:text-white" x-text="`Total Rp ${money(tempoForm.total_amount)}`"></div></div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Due date</label><input x-model="tempoForm.due_date" type="date" name="due_date" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Payer type</label><select x-model="tempoForm.payer_type" name="payer_type" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="self_pay">Self-pay</option><option value="corporate">Perusahaan</option></select></div>
                </div>
                <div class="grid gap-5 md:grid-cols-3" x-show="tempoForm.payer_type === 'corporate'">
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama perusahaan</label><input x-model="tempoForm.payer_name" type="text" name="payer_name" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">PIC</label><input x-model="tempoForm.payer_contact_person" type="text" name="payer_contact_person" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label><input x-model="tempoForm.payer_phone" type="text" name="payer_phone" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                </div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Catatan tempo</label><textarea x-model="tempoForm.notes" name="notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                <div class="flex justify-end gap-3"><x-ui.button type="button" variant="outline" x-on:click="tempoModalOpen = false">Batal</x-ui.button><x-ui.button type="submit">Buka Tempo</x-ui.button></div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="voidModalOpen" maxWidth="2xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white">Void / cancel invoice</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Hanya admin yang bisa melakukan void atau cancel. Alasan wajib diisi untuk kebutuhan audit.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="voidModalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div>
            <form method="POST" x-bind:action="`${payBase}/${voidForm.id}/void`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="invoice-void">
                <input type="hidden" name="entity_id" x-bind:value="voidForm.id">
                <input type="hidden" name="invoice_no" x-bind:value="voidForm.invoice_no">
                <input type="hidden" name="patient" x-bind:value="voidForm.patient">
                <input type="hidden" name="total_amount" x-bind:value="voidForm.total_amount">
                <div class="rounded-2xl border border-red-200 bg-red-50 p-4 dark:border-red-500/20 dark:bg-red-500/10"><div class="font-medium text-gray-900 dark:text-white" x-text="voidForm.invoice_no"></div><div class="mt-1 text-sm text-gray-600 dark:text-gray-300" x-text="voidForm.patient"></div><div class="mt-2 text-base font-semibold text-red-700 dark:text-red-300" x-text="`Total Rp ${money(voidForm.total_amount)}`"></div></div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alasan void / cancel</label><textarea x-model="voidForm.void_reason" name="void_reason" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Catatan admin</label><textarea x-model="voidForm.notes" name="notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                <div class="flex justify-end gap-3"><x-ui.button type="button" variant="outline" x-on:click="voidModalOpen = false">Batal</x-ui.button><button type="submit" class="inline-flex items-center justify-center rounded-lg bg-red-600 px-5 py-3.5 text-sm font-medium text-white transition hover:bg-red-700">Simpan Void</button></div>
            </form>
        </x-ui.modal>
    </div>
@endsection

