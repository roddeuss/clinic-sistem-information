@php
    $defaultCounterId = (string) ($counterOptions->first()?->id ?? '');
    $openModalOpen = $errors->any() && old('form_context') === 'cashier-shift-open';
    $closeModalOpen = $errors->any() && old('form_context') === 'cashier-shift-close';
    $openForm = [
        'counter_id' => (string) old('counter_id', $defaultCounterId),
        'opening_balance' => old('opening_balance', '0'),
        'opening_notes' => old('opening_notes', ''),
    ];
    $closeForm = [
        'id' => old('entity_id'),
        'shift_code' => old('shift_code', ''),
        'counter_name' => old('counter_name', ''),
        'user_name' => old('user_name', ''),
        'closing_balance' => old('closing_balance', ''),
        'closing_notes' => old('closing_notes', ''),
    ];
    $activeShiftClosePayload = $activeShift
        ? [
            'id' => $activeShift->id,
            'shift_code' => $activeShift->shift_code,
            'counter_name' => ($activeShift->counter?->code ?? '-') . ' - ' . ($activeShift->counter?->name ?? '-'),
            'user_name' => $activeShift->user?->name ?? '-',
            'closing_balance' => '',
            'closing_notes' => '',
        ]
        : null;
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Cashier Shifts" />

    <div x-data="{
        openModalOpen: @js($openModalOpen),
        closeModalOpen: @js($closeModalOpen),
        storeAction: @js(route('cashier-shifts.store')),
        closeBase: @js(url('/cashier-shifts')),
        openForm: @js($openForm),
        closeForm: @js($closeForm),
        emptyOpenForm() { return { counter_id:@js($defaultCounterId), opening_balance:'0', opening_notes:'' }; },
        openCreate() { this.openForm = this.emptyOpenForm(); this.openModalOpen = true; },
        openClose(payload) { this.closeForm = payload; this.closeModalOpen = true; },
    }" @keydown.escape.window="openModalOpen = false; closeModalOpen = false" class="space-y-6">
        @if (session('status'))
            <x-ui.alert variant="success" :message="session('status')" />
        @endif
        @if ($errors->any())
            <x-ui.alert variant="error" :message="$errors->first()" />
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Cashier Shifts</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">Sesi kerja kasir per user dan counter. Semua pembayaran invoice akan tertaut ke shift aktif ini.</p>
                </div>
                @if ($abilities['open'])
                    <x-ui.button type="button" x-on:click="openCreate()">Buka Shift</x-ui.button>
                @endif
            </div>
        </section>

        @if ($activeShift)
            <section class="rounded-2xl border border-brand-200 bg-brand-50/70 p-6 shadow-theme-sm dark:border-brand-500/20 dark:bg-brand-500/10">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-600 dark:text-brand-300">Active Shift</div>
                        <h2 class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $activeShift->shift_code }}</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $activeShift->user?->name }} | {{ $activeShift->counter?->code }} - {{ $activeShift->counter?->name }} | {{ $activeShift->branch?->code }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Dibuka {{ $activeShift->opened_at?->format('d M Y H:i') }} dengan saldo awal Rp {{ number_format((float) $activeShift->opening_balance, 0, ',', '.') }}</p>
                    </div>
                    @if ($abilities['close'])
                        <button type="button" data-payload='@json($activeShiftClosePayload)' x-on:click='openClose(JSON.parse($el.dataset.payload))' class="inline-flex h-11 items-center rounded-xl bg-white px-4 text-sm font-medium text-gray-700 shadow-theme-xs ring-1 ring-gray-200 transition hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:ring-gray-700 dark:hover:bg-white/[0.03]">Tutup Shift Aktif</button>
                    @endif
                </div>
            </section>
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('cashier-shifts') }}" class="grid gap-4 md:grid-cols-[1.1fr_220px_180px_180px_auto]">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari shift, user, atau counter" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <select name="branch" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua branch</option>@foreach ($branchOptions as $branchOption)<option value="{{ $branchOption->id }}" @selected($filters['branch'] === (string) $branchOption->id)>{{ $branchOption->code }} - {{ $branchOption->name }}</option>@endforeach</select>
                <select name="status" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua status</option><option value="open" @selected($filters['status'] === 'open')>Open</option><option value="closed" @selected($filters['status'] === 'closed')>Closed</option></select>
                <input type="date" name="date" value="{{ $filters['date'] }}" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <div class="flex gap-3"><x-ui.button type="submit" variant="outline">Filter</x-ui.button><a href="{{ route('cashier-shifts') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a></div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Shift list</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $shifts->firstItem() ?? 0 }} - {{ $shifts->lastItem() ?? 0 }} dari {{ $shifts->total() }} shift.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Shift</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Counter</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Session</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Invoice</th><th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($shifts as $shift)
                            @php
                                $closePayload = [
                                    'id' => $shift->id,
                                    'shift_code' => $shift->shift_code,
                                    'counter_name' => ($shift->counter?->code ?? '-') . ' - ' . ($shift->counter?->name ?? '-'),
                                    'user_name' => $shift->user?->name ?? '-',
                                    'closing_balance' => '',
                                    'closing_notes' => '',
                                ];
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $shift->shift_code }}</div>
                                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $shift->user?->name }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $shift->branch?->code }} | {{ ucfirst($shift->status) }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $shift->counter?->code }} - {{ $shift->counter?->name }}</div>
                                    <div class="mt-1 text-xs text-gray-400">
                                        Saldo awal Rp {{ number_format((float) $shift->opening_balance, 0, ',', '.') }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>Buka {{ $shift->opened_at?->format('d M Y H:i') }}</div>
                                    <div class="mt-1 text-xs text-gray-400">
                                        {{ $shift->closed_at ? 'Tutup ' . $shift->closed_at->format('d M Y H:i') : 'Masih open' }}
                                    </div>
                                    @if ($shift->closed_at)
                                        <div class="mt-1 text-xs text-gray-400">
                                            Actual cash Rp {{ number_format((float) $shift->closing_balance, 0, ',', '.') }}
                                        </div>
                                        <div class="mt-1 text-xs {{ (float) $shift->cash_variance === 0.0 ? 'text-gray-400' : 'text-amber-600 dark:text-amber-300' }}">
                                            Variance Rp {{ number_format((float) $shift->cash_variance, 0, ',', '.') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $shift->invoices_count }} invoice</div>
                                    @if ($shift->closed_at)
                                        <div class="mt-1 text-xs text-gray-400">
                                            Expected cash Rp {{ number_format((float) $shift->expected_cash_total, 0, ',', '.') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['close'] && $shift->status === 'open')
                                            <button
                                                type="button"
                                                title="Tutup shift"
                                                data-payload='@json($closePayload)' x-on:click='openClose(JSON.parse($el.dataset.payload))'
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 text-white shadow-theme-xs transition hover:bg-brand-600"
                                            >
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M6 12L10 16L18 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada cashier shift.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $shifts->links() }}</div>
        </section>

        <x-ui.modal show="openModalOpen" maxWidth="2xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white">Buka Cashier Shift</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Shift baru akan mengaktifkan counter dan sesi kerja kasir saat ini.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="openModalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div>
            <form method="POST" action="{{ route('cashier-shifts.store') }}" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="cashier-shift-open">
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Counter</label><select x-model="openForm.counter_id" name="counter_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">@foreach ($counterOptions as $counterOption)<option value="{{ $counterOption->id }}">{{ $counterOption->branch?->code }} | {{ $counterOption->code }} - {{ $counterOption->name }}</option>@endforeach</select></div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Opening balance</label><input x-model="openForm.opening_balance" type="number" step="0.01" min="0" name="opening_balance" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Opening notes</label><textarea x-model="openForm.opening_notes" name="opening_notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                <div class="flex justify-end gap-3"><x-ui.button type="button" variant="outline" x-on:click="openModalOpen = false">Batal</x-ui.button><x-ui.button type="submit">Buka Shift</x-ui.button></div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="closeModalOpen" maxWidth="2xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white">Tutup Cashier Shift</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Isi hasil hitung kas aktual untuk mencatat selisih kas saat handover.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="closeModalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div>
            <form method="POST" x-bind:action="`${closeBase}/${closeForm.id}/close`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="cashier-shift-close">
                <input type="hidden" name="entity_id" x-bind:value="closeForm.id">
                <input type="hidden" name="shift_code" x-bind:value="closeForm.shift_code">
                <input type="hidden" name="counter_name" x-bind:value="closeForm.counter_name">
                <input type="hidden" name="user_name" x-bind:value="closeForm.user_name">
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="font-medium text-gray-900 dark:text-white" x-text="closeForm.shift_code"></div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="`${closeForm.user_name} | ${closeForm.counter_name}`"></div></div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Closing balance</label><input x-model="closeForm.closing_balance" type="number" step="0.01" min="0" name="closing_balance" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Closing notes</label><textarea x-model="closeForm.closing_notes" name="closing_notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                <div class="flex justify-end gap-3"><x-ui.button type="button" variant="outline" x-on:click="closeModalOpen = false">Batal</x-ui.button><x-ui.button type="submit">Tutup Shift</x-ui.button></div>
            </form>
        </x-ui.modal>
    </div>
@endsection

