@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Audit Logs" />

    <div x-data="{ detailModalOpen: false, detail: null, openDetail(payload) { this.detail = payload; this.detailModalOpen = true; } }" @keydown.escape.window="detailModalOpen = false" class="space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Audit Logs</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">Jejak aktivitas untuk master data dan transaksi penting, termasuk invoice, payment method, supplier, product category, dan cashier shift.</p>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('audit-logs') }}" class="grid gap-4 md:grid-cols-[1.2fr_180px_180px_220px_220px_180px_auto]">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari module, action, user, atau description" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <select name="module" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua module</option>@foreach ($moduleOptions as $moduleOption)<option value="{{ $moduleOption }}" @selected($filters['module'] === $moduleOption)>{{ $moduleOption }}</option>@endforeach</select>
                <select name="action" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua action</option>@foreach ($actionOptions as $actionOption)<option value="{{ $actionOption }}" @selected($filters['action'] === $actionOption)>{{ $actionOption }}</option>@endforeach</select>
                <select name="branch" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua branch</option>@foreach ($branchOptions as $branchOption)<option value="{{ $branchOption->id }}" @selected($filters['branch'] === (string) $branchOption->id)>{{ $branchOption->code }} - {{ $branchOption->name }}</option>@endforeach</select>
                <select name="user" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua user</option>@foreach ($userOptions as $userOption)<option value="{{ $userOption->id }}" @selected($filters['user'] === (string) $userOption->id)>{{ $userOption->name }}</option>@endforeach</select>
                <input type="date" name="date" value="{{ $filters['date'] }}" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <div class="flex gap-3"><x-ui.button type="submit" variant="outline">Filter</x-ui.button><a href="{{ route('audit-logs') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a></div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Audit trail</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $logs->firstItem() ?? 0 }} - {{ $logs->lastItem() ?? 0 }} dari {{ $logs->total() }} log.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">When</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Module</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actor</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Description</th><th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($logs as $log)
                            @php
                                $payload = [
                                    'module' => $log->module,
                                    'action' => $log->action,
                                    'description' => $log->description,
                                    'user' => $log->user?->name,
                                    'branch' => $log->branch?->code,
                                    'created_at' => $log->created_at?->format('d M Y H:i:s'),
                                    'ip_address' => $log->ip_address,
                                    'before_data' => $log->before_data,
                                    'after_data' => $log->after_data,
                                    'meta' => $log->meta,
                                ];
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $log->created_at?->format('d M Y H:i:s') }}<div class="mt-1 text-xs text-gray-400">{{ $log->ip_address ?: '-' }}</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><div>{{ $log->module }}</div><div class="mt-1 text-xs text-gray-400">{{ $log->action }}</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $log->user?->name ?? 'System' }}<div class="mt-1 text-xs text-gray-400">{{ $log->branch?->code ?? '-' }}</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $log->description ?: '-' }}</td>
                                <td class="px-6 py-4"><div class="flex justify-end gap-2"><x-ui.icon-button title="Lihat detail" data-payload='@json($payload)' x-on:click='openDetail(JSON.parse($el.dataset.payload))'><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 12C4.8 8.4 8.1 6.5 12 6.5C15.9 6.5 19.2 8.4 21 12C19.2 15.6 15.9 17.5 12 17.5C8.1 17.5 4.8 15.6 3 12Z" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.5"/></svg></x-ui.icon-button></div></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada audit log.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $logs->links() }}</div>
        </section>

        <x-ui.modal show="detailModalOpen" maxWidth="4xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white">Audit detail</h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Snapshot before dan after dari event yang dipilih.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="detailModalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div>
            <div class="space-y-5 p-6" x-show="detail">
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Event</div><div class="mt-2 font-semibold text-gray-900 dark:text-white" x-text="detail?.module"></div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="detail?.action"></div><div class="mt-1 text-xs text-gray-400" x-text="detail?.created_at"></div></div>
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]"><div class="text-xs uppercase tracking-[0.14em] text-gray-400">Actor</div><div class="mt-2 font-semibold text-gray-900 dark:text-white" x-text="detail?.user || 'System'"></div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="detail?.branch || '-'"></div><div class="mt-1 text-xs text-gray-400" x-text="detail?.ip_address || '-'"></div></div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300" x-text="detail?.description || '-'"></div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div><div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Before</div><pre class="min-h-[180px] overflow-auto rounded-2xl border border-gray-200 bg-gray-50 p-4 text-xs text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300" x-text="JSON.stringify(detail?.before_data ?? {}, null, 2)"></pre></div>
                    <div><div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">After</div><pre class="min-h-[180px] overflow-auto rounded-2xl border border-gray-200 bg-gray-50 p-4 text-xs text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300" x-text="JSON.stringify(detail?.after_data ?? {}, null, 2)"></pre></div>
                </div>
                <div><div class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Meta</div><pre class="min-h-[120px] overflow-auto rounded-2xl border border-gray-200 bg-gray-50 p-4 text-xs text-gray-600 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300" x-text="JSON.stringify(detail?.meta ?? {}, null, 2)"></pre></div>
            </div>
        </x-ui.modal>
    </div>
@endsection

