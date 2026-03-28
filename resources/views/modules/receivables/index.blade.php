@php
    $defaultPaymentMethodId = (string) ($paymentMethodOptions->first()?->id ?? '');
    $extendModalOpen = $errors->any() && old('form_context') === 'receivable-extend';
    $settleModalOpen = $errors->any() && old('form_context') === 'receivable-settle';
    $cancelModalOpen = $errors->any() && old('form_context') === 'receivable-cancel';
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Receivables" />

    <div
        x-data="{
            detailModalOpen: false,
            extendModalOpen: @js($extendModalOpen),
            settleModalOpen: @js($settleModalOpen),
            cancelModalOpen: @js($cancelModalOpen),
            detail: null,
            base: @js(url('/receivables')),
            defaultPaymentMethodId: @js($defaultPaymentMethodId),
            extendForm: { id: @js(old('entity_id')), due_date: @js(old('due_date', $defaultDueDate)), extension_reason: @js(old('extension_reason', '')), notes: @js(old('notes', '')) },
            settleForm: { id: @js(old('entity_id')), payment_method_id: @js(old('payment_method_id', $defaultPaymentMethodId)), amount: @js(old('amount', 0)), payment_reference: @js(old('payment_reference', '')), notes: @js(old('notes', '')) },
            cancelForm: { id: @js(old('entity_id')), cancel_reason: @js(old('cancel_reason', '')), notes: @js(old('notes', '')) },
            money(value) { return new Intl.NumberFormat('id-ID').format(Number(value || 0)); },
            openDetail(payload) { this.detail = payload; this.detailModalOpen = true; },
            openExtend(payload) { this.detail = payload; this.extendForm = { id: payload.id, due_date: payload.due_date_raw, extension_reason: '', notes: payload.notes || '' }; this.extendModalOpen = true; },
            openSettle(payload) { this.detail = payload; this.settleForm = { id: payload.id, payment_method_id: this.defaultPaymentMethodId, amount: payload.outstanding_amount, payment_reference: '', notes: payload.notes || '' }; this.settleModalOpen = true; },
            openCancel(payload) { this.detail = payload; this.cancelForm = { id: payload.id, cancel_reason: '', notes: payload.notes || '' }; this.cancelModalOpen = true; },
        }"
        @keydown.escape.window="detailModalOpen = false; extendModalOpen = false; settleModalOpen = false; cancelModalOpen = false"
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Receivables</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">Invoice tempo untuk self-pay maupun perusahaan. Modul ini sekarang mendukung cicilan, extend jatuh tempo, dan pelunasan akhir dalam cashier shift aktif.</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300">
                    <div class="font-medium text-gray-900 dark:text-white">Default due date</div>
                    <div class="mt-1">Tempo baru mengikuti default +7 hari, lalu bisa diperpanjang bila diperlukan.</div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('receivables') }}" class="grid gap-4 md:grid-cols-[1.2fr_220px_180px_180px_auto]">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari invoice, patient, RM, HP, atau perusahaan" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <select name="branch" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Semua branch</option>
                    @foreach ($branchOptions as $branchOption)
                        <option value="{{ $branchOption->id }}" @selected($filters['branch'] === (string) $branchOption->id)>{{ $branchOption->code }} - {{ $branchOption->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Semua status</option>
                    <option value="open" @selected($filters['status'] === 'open')>Open</option>
                    <option value="overdue" @selected($filters['status'] === 'overdue')>Overdue</option>
                    <option value="settled" @selected($filters['status'] === 'settled')>Settled</option>
                    <option value="cancelled" @selected($filters['status'] === 'cancelled')>Cancelled</option>
                </select>
                <input type="date" name="date" value="{{ $filters['date'] }}" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <div class="flex gap-3">
                    <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
                    <a href="{{ route('receivables') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Receivable list</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $receivables->firstItem() ?? 0 }} - {{ $receivables->lastItem() ?? 0 }} dari {{ $receivables->total() }} receivable.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Invoice</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Payer</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Due date</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Settlement</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($receivables as $receivable)
                            @php
                                $payload = [
                                    'id' => $receivable->id,
                                    'invoice_no' => $receivable->invoice?->invoice_no,
                                    'patient' => $receivable->patient?->full_name,
                                    'medical_record_no' => $receivable->patientBranchRecord?->medical_record_no,
                                    'phone' => $receivable->patient?->phone,
                                    'branch' => $receivable->branch?->code,
                                    'payer_type' => $receivable->invoice?->payer_type,
                                    'payer_name' => $receivable->invoice?->payer_name,
                                    'payer_contact_person' => $receivable->invoice?->payer_meta['company_contact_person'] ?? null,
                                    'payer_phone' => $receivable->invoice?->payer_meta['company_phone'] ?? null,
                                    'status' => $receivable->status,
                                    'due_date' => $receivable->due_date?->format('d M Y'),
                                    'due_date_raw' => $receivable->due_date?->toDateString(),
                                    'opened_at' => $receivable->opened_at?->format('d M Y H:i'),
                                    'opened_by' => $receivable->openedBy?->name,
                                    'extended_at' => $receivable->extended_at?->format('d M Y H:i'),
                                    'extended_by' => $receivable->extendedBy?->name,
                                    'extension_reason' => $receivable->extension_reason,
                                    'settled_at' => $receivable->settled_at?->format('d M Y H:i'),
                                    'settled_by' => $receivable->settledBy?->name,
                                    'cancelled_at' => $receivable->cancelled_at?->format('d M Y H:i'),
                                    'cancelled_by' => $receivable->cancelledBy?->name,
                                    'cancel_reason' => $receivable->cancel_reason,
                                    'total_amount' => (float) ($receivable->invoice?->total_amount ?? 0),
                                    'paid_amount' => (float) ($receivable->invoice?->paid_amount ?? 0),
                                    'outstanding_amount' => max(0, (float) ($receivable->invoice?->total_amount ?? 0) - (float) ($receivable->invoice?->paid_amount ?? 0)),
                                    'payment_method' => $receivable->invoice?->paymentMethod?->name,
                                    'cashier_shift' => $receivable->invoice?->cashierShift?->shift_code,
                                    'cashier_counter' => $receivable->invoice?->cashierShift?->counter?->code,
                                    'notes' => $receivable->notes,
                                    'payments' => $receivable->invoice?->payments?->map(fn ($payment) => [
                                        'amount' => (float) $payment->amount,
                                        'payment_method' => $payment->paymentMethod?->name ?? '-',
                                        'payment_reference' => $payment->payment_reference,
                                        'payment_date' => $payment->payment_date?->format('d M Y H:i'),
                                        'cashier_shift' => $payment->cashierShift?->shift_code,
                                        'cashier_counter' => $payment->cashierShift?->counter?->code,
                                        'paid_by' => $payment->paidBy?->name,
                                    ])->values()->all() ?? [],
                                ];
                                $statusClasses = match ($receivable->status) {
                                    'settled' => 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300',
                                    'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-300',
                                    'overdue' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                                    default => 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300',
                                };
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4"><div class="font-medium text-gray-900 dark:text-white">{{ $receivable->invoice?->invoice_no }}</div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $receivable->branch?->code }} | Rp {{ number_format((float) ($receivable->invoice?->total_amount ?? 0), 0, ',', '.') }}</div><div class="mt-1 text-xs text-gray-400">Opened {{ $receivable->opened_at?->format('d M Y H:i') }}</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><div class="font-medium text-gray-900 dark:text-white">{{ $receivable->invoice?->payer_type === 'corporate' ? ($receivable->invoice?->payer_name ?: 'Perusahaan') : ($receivable->patient?->full_name ?: '-') }}</div><div class="mt-1 text-xs text-gray-400">{{ $receivable->patientBranchRecord?->medical_record_no }} | {{ $receivable->patient?->phone }}</div><div class="mt-1 text-xs text-gray-400">{{ $receivable->invoice?->payer_type === 'corporate' ? 'Corporate receivable' : 'Self-pay receivable' }}</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><div>{{ $receivable->due_date?->format('d M Y') }}</div><div class="mt-1 text-xs text-gray-400">{{ $receivable->extended_at ? 'Extended ' . $receivable->extended_at->format('d M Y H:i') : 'Default tempo' }}</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><div>Paid Rp {{ number_format((float) ($receivable->invoice?->paid_amount ?? 0), 0, ',', '.') }}</div><div class="mt-1 text-xs text-gray-400">Outstanding Rp {{ number_format(max(0, (float) ($receivable->invoice?->total_amount ?? 0) - (float) ($receivable->invoice?->paid_amount ?? 0)), 0, ',', '.') }}</div><div class="mt-1 text-xs text-gray-400">{{ $receivable->settled_at ? 'Lunas ' . $receivable->settled_at->format('d M Y H:i') : ($receivable->cancelled_at ? 'Cancelled ' . $receivable->cancelled_at->format('d M Y H:i') : 'Menunggu pelunasan') }}</div></td>
                                <td class="px-6 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses }}">{{ ucfirst($receivable->status) }}</span></td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        <x-ui.icon-button title="Detail receivable" data-payload='@json($payload)' x-on:click='openDetail(JSON.parse($el.dataset.payload))'><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 12C4.8 8.4 8.1 6.5 12 6.5C15.9 6.5 19.2 8.4 21 12C19.2 15.6 15.9 17.5 12 17.5C8.1 17.5 4.8 15.6 3 12Z" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.5"/></svg></x-ui.icon-button>
                                        @if ($abilities['extend'] && in_array($receivable->status, ['open', 'overdue'], true))
                                            <x-ui.icon-button title="Extend due date" data-payload='@json($payload)' x-on:click='openExtend(JSON.parse($el.dataset.payload))'><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M8 2V5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M16 2V5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M3.5 9.5H20.5" stroke="currentColor" stroke-width="1.5"/><rect x="3.5" y="4.5" width="17" height="16" rx="2.5" stroke="currentColor" stroke-width="1.5"/><path d="M12 12V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 14H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button>
                                        @endif
                                        @if ($abilities['settle'] && in_array($receivable->status, ['open', 'overdue'], true))
                                            <x-ui.icon-button variant="primary" title="Settle receivable" data-payload='@json($payload)' x-on:click='openSettle(JSON.parse($el.dataset.payload))'><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="3.75" y="6.75" width="16.5" height="10.5" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M3.75 10.5H20.25" stroke="currentColor" stroke-width="1.5"/></svg></x-ui.icon-button>
                                        @endif
                                        @if ($abilities['cancel'] && in_array($receivable->status, ['open', 'overdue'], true))
                                            <x-ui.icon-button variant="danger" title="Cancel receivable" data-payload='@json($payload)' x-on:click='openCancel(JSON.parse($el.dataset.payload))'><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada receivable.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $receivables->links() }}</div>
        </section>

        <x-ui.modal show="detailModalOpen" maxWidth="4xl"><div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white">Receivable detail</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Status tempo, payer, riwayat extension, dan histori cicilan.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="detailModalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div><div class="space-y-5 p-6" x-show="detail"><div class="grid gap-5 md:grid-cols-3"><div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Invoice</div><div class="mt-2 font-semibold text-gray-900 dark:text-white" x-text="detail?.invoice_no"></div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="`${detail?.branch ?? '-'} | ${detail?.status ?? '-'}`"></div></div><div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Payer</div><div class="mt-2 font-semibold text-gray-900 dark:text-white" x-text="detail?.payer_type === 'corporate' ? (detail?.payer_name || 'Perusahaan') : detail?.patient"></div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="detail?.payer_type === 'corporate' ? `${detail?.payer_contact_person || '-'} | ${detail?.payer_phone || '-'}` : `${detail?.medical_record_no ?? '-'} | ${detail?.phone ?? '-'}`"></div></div><div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Financials</div><div class="mt-2 font-semibold text-gray-900 dark:text-white" x-text="`Total Rp ${money(detail?.total_amount)}`"></div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="`Paid Rp ${money(detail?.paid_amount)}`"></div><div class="mt-1 text-xs text-gray-400" x-text="`Outstanding Rp ${money(detail?.outstanding_amount)}`"></div></div></div><div class="grid gap-5 md:grid-cols-2"><div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Opened</div><div class="mt-2" x-text="detail?.opened_at ? `${detail.opened_at} by ${detail?.opened_by ?? '-'}` : '-'"></div></div><div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Extension</div><div class="mt-2" x-text="detail?.extended_at ? `${detail.extended_at} by ${detail?.extended_by ?? '-'}${detail?.extension_reason ? ' | ' + detail.extension_reason : ''}` : 'Belum ada extension'"></div></div><div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Settlement</div><div class="mt-2" x-text="detail?.settled_at ? `${detail.settled_at} by ${detail?.settled_by ?? '-'}` : 'Belum lunas'"></div><div class="mt-1 text-xs text-gray-400" x-text="detail?.cashier_shift ? `${detail.cashier_shift} | ${detail?.cashier_counter ?? '-'}` : ''"></div></div><div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Cancel</div><div class="mt-2" x-text="detail?.cancelled_at ? `${detail.cancelled_at} by ${detail?.cancelled_by ?? '-'}${detail?.cancel_reason ? ' | ' + detail.cancel_reason : ''}` : 'Masih aktif'"></div></div></div><div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Notes</div><div class="mt-2" x-text="detail?.notes || '-'"></div></div><div class="rounded-2xl border border-gray-200 dark:border-gray-800" x-show="detail?.payments?.length"><div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-900 dark:border-gray-800 dark:text-white">Riwayat cicilan</div><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800"><thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Tanggal</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Method</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Amount</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Shift</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">By</th></tr></thead><tbody class="divide-y divide-gray-200 dark:divide-gray-800"><template x-for="payment in detail?.payments ?? []" :key="`${payment.payment_date}-${payment.amount}`"><tr><td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" x-text="payment.payment_date || '-'"></td><td class="px-4 py-3 text-sm text-gray-900 dark:text-white"><div x-text="payment.payment_method || '-'"></div><div class="mt-1 text-xs text-gray-400" x-text="payment.payment_reference || '-'"></div></td><td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" x-text="`Rp ${money(payment.amount)}`"></td><td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" x-text="payment.cashier_shift ? `${payment.cashier_shift} | ${payment.cashier_counter ?? '-'}` : '-'"></td><td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" x-text="payment.paid_by || '-'"></td></tr></template></tbody></table></div></div></div></x-ui.modal>

        <x-ui.modal show="extendModalOpen" maxWidth="2xl"><div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white">Extend receivable</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Perpanjang jatuh tempo invoice tempo dengan alasan yang terdokumentasi.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="extendModalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div><form method="POST" x-bind:action="`${base}/${extendForm.id}/extend`" class="space-y-5 p-6">@csrf<input type="hidden" name="form_context" value="receivable-extend"><input type="hidden" name="entity_id" x-bind:value="extendForm.id"><div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Due date</label><input x-model="extendForm.due_date" type="date" name="due_date" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div><div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alasan extend</label><textarea x-model="extendForm.extension_reason" name="extension_reason" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div><div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label><textarea x-model="extendForm.notes" name="notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div><div class="flex justify-end gap-3"><x-ui.button type="button" variant="outline" x-on:click="extendModalOpen = false">Batal</x-ui.button><x-ui.button type="submit">Simpan Extend</x-ui.button></div></form></x-ui.modal>

        <x-ui.modal show="settleModalOpen" maxWidth="2xl"><div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white">Catat pembayaran receivable</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pembayaran bisa dicicil. Saat outstanding habis, receivable otomatis berubah menjadi settled.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="settleModalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div><form method="POST" x-bind:action="`${base}/${settleForm.id}/settle`" class="space-y-5 p-6">@csrf<input type="hidden" name="form_context" value="receivable-settle"><input type="hidden" name="entity_id" x-bind:value="settleForm.id"><div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="font-medium text-gray-900 dark:text-white" x-text="detail?.invoice_no"></div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="detail?.payer_type === 'corporate' ? (detail?.payer_name || 'Perusahaan') : detail?.patient"></div><div class="mt-2 text-base font-semibold text-gray-900 dark:text-white" x-text="`Outstanding Rp ${money(detail?.outstanding_amount)}`"></div><div class="mt-1 text-xs text-gray-400">@if ($activeShift)Shift aktif: {{ $activeShift->shift_code }} | {{ $activeShift->branch?->code }} | {{ $activeShift->counter?->code }} - {{ $activeShift->counter?->name }}@else Shift aktif belum tersedia.@endif</div></div><div class="grid gap-5 md:grid-cols-3"><div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Payment method</label><select x-model="settleForm.payment_method_id" name="payment_method_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Pilih payment method</option>@foreach ($paymentMethodOptions as $paymentMethodOption)<option value="{{ $paymentMethodOption->id }}">{{ $paymentMethodOption->name }} ({{ $paymentMethodOption->code }})</option>@endforeach</select></div><div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nominal bayar</label><input x-model="settleForm.amount" type="number" step="0.01" min="0.01" name="amount" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div><div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Payment reference</label><input x-model="settleForm.payment_reference" type="text" name="payment_reference" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div></div><div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label><textarea x-model="settleForm.notes" name="notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div><div class="flex justify-end gap-3"><x-ui.button type="button" variant="outline" x-on:click="settleModalOpen = false">Batal</x-ui.button><button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-3.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:bg-brand-300" @disabled(! $activeShift || $paymentMethodOptions->isEmpty()) x-text="Number(settleForm.amount || 0) >= Number(detail?.outstanding_amount || 0) ? 'Tandai Paid' : 'Catat Cicilan'"></button></div></form></x-ui.modal>

        <x-ui.modal show="cancelModalOpen" maxWidth="2xl"><div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white">Cancel receivable</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Hanya admin yang bisa membatalkan receivable aktif. Alasan wajib untuk audit.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="cancelModalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div><form method="POST" x-bind:action="`${base}/${cancelForm.id}/cancel`" class="space-y-5 p-6">@csrf<input type="hidden" name="form_context" value="receivable-cancel"><input type="hidden" name="entity_id" x-bind:value="cancelForm.id"><div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alasan cancel</label><textarea x-model="cancelForm.cancel_reason" name="cancel_reason" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div><div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label><textarea x-model="cancelForm.notes" name="notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div><div class="flex justify-end gap-3"><x-ui.button type="button" variant="outline" x-on:click="cancelModalOpen = false">Batal</x-ui.button><button type="submit" class="inline-flex items-center justify-center rounded-lg bg-red-600 px-5 py-3.5 text-sm font-medium text-white transition hover:bg-red-700">Simpan Cancel</button></div></form></x-ui.modal>
    </div>
@endsection
