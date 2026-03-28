@php
    $expiryModalOpen = $errors->any() && old('form_context') === 'expiry-monitoring-update';
    $expiryModalForm = [
        'id' => old('entity_id'),
        'batch_number' => old('batch_number', ''),
        'medicine_label' => old('medicine_label', ''),
        'branch_label' => old('branch_label', ''),
        'supplier_label' => old('supplier_label', ''),
        'expired_at' => old('expired_at', ''),
        'action' => old('action', 'quarantine'),
        'quarantine_reason' => old('quarantine_reason', ''),
    ];
    $today = now()->startOfDay();
    $windowEnd = now()->addDays((int) $filters['window_days'])->startOfDay();
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Expiry Monitoring" />

    <div
        x-data="{
            expiryModalOpen: @js($expiryModalOpen),
            updateBase: @js(url('/expiry-monitoring')),
            form: @js($expiryModalForm),
            emptyForm() {
                return {
                    id: null,
                    batch_number: '',
                    medicine_label: '',
                    branch_label: '',
                    supplier_label: '',
                    expired_at: '',
                    action: 'quarantine',
                    quarantine_reason: '',
                };
            },
            openAction(payload) {
                this.form = {
                    ...this.emptyForm(),
                    ...payload,
                };

                if (this.form.action === 'release') {
                    this.form.quarantine_reason = '';
                }

                this.expiryModalOpen = true;
            },
        }"
        @keydown.escape.window="expiryModalOpen = false"
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Expiry Monitoring</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Pantau batch yang expired, mendekati expired, atau perlu dikarantina. Batch non-expired bisa
                        di-release kembali ke stok aktif saat kondisinya sudah aman.
                    </p>
                </div>
                <div class="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                    Default tampilan menyorot batch yang butuh perhatian.
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('expiry-monitoring') }}" class="grid gap-4 md:grid-cols-[1.1fr_220px_200px_160px_auto]">
                <input
                    type="text"
                    name="search"
                    value="{{ $filters['search'] }}"
                    placeholder="Cari batch atau medicine"
                    class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >

                <select
                    name="branch"
                    class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                    <option value="">Semua branch</option>
                    @foreach ($branchOptions as $branchOption)
                        <option value="{{ $branchOption->id }}" @selected($filters['branch'] === (string) $branchOption->id)>
                            {{ $branchOption->code }} - {{ $branchOption->name }}
                        </option>
                    @endforeach
                </select>

                <select
                    name="status"
                    class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                    <option value="attention" @selected($filters['status'] === 'attention')>Perlu perhatian</option>
                    <option value="expired" @selected($filters['status'] === 'expired')>Expired</option>
                    <option value="near_expiry" @selected($filters['status'] === 'near_expiry')>Near expiry</option>
                    <option value="quarantined" @selected($filters['status'] === 'quarantined')>Quarantined</option>
                    <option value="healthy" @selected($filters['status'] === 'healthy')>Healthy</option>
                </select>

                <input
                    type="number"
                    name="window_days"
                    min="1"
                    max="180"
                    value="{{ $filters['window_days'] }}"
                    class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >

                <div class="flex gap-3">
                    <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
                    <a
                        href="{{ route('expiry-monitoring') }}"
                        class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300"
                    >
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Batch monitoring list</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Menampilkan {{ $batches->firstItem() ?? 0 }} - {{ $batches->lastItem() ?? 0 }} dari {{ $batches->total() }}
                    batch.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Batch</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Medicine</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Supplier</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Stock</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Expiry</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($batches as $batch)
                            @php
                                $expiryDate = $batch->expired_at?->copy()->startOfDay();
                                $isExpired = $expiryDate?->lt($today) ?? false;
                                $isNearExpiry = ! $isExpired && $expiryDate?->lte($windowEnd);
                                $isQuarantined = $batch->isQuarantined();
                                $daysLeft = $expiryDate ? $today->diffInDays($expiryDate, false) : null;
                                $expiryClasses = match (true) {
                                    $isExpired => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-300',
                                    $isNearExpiry => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                                    default => 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300',
                                };
                                $stockStatusClasses = match (true) {
                                    $isQuarantined => 'bg-slate-100 text-slate-700 dark:bg-white/[0.05] dark:text-slate-300',
                                    ! $batch->is_active => 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300',
                                    default => 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300',
                                };
                                $expiryLabel = match (true) {
                                    $isExpired => 'Expired',
                                    $isNearExpiry => 'Near expiry',
                                    default => 'Healthy',
                                };
                                $stockStatusLabel = match (true) {
                                    $isQuarantined => 'Quarantined',
                                    ! $batch->is_active => 'Inactive',
                                    default => 'Active',
                                };
                                $actionPayload = [
                                    'id' => $batch->id,
                                    'batch_number' => $batch->batch_number,
                                    'medicine_label' => trim(($batch->medicine?->code ?? '-') . ' - ' . ($batch->medicine?->name ?? '-')),
                                    'branch_label' => trim(($batch->branch?->code ?? '-') . ' - ' . ($batch->branch?->name ?? '-')),
                                    'supplier_label' => trim(($batch->supplier?->code ?? '-') . ' - ' . ($batch->supplier?->name ?? '-')),
                                    'expired_at' => $batch->expired_at?->format('d M Y'),
                                    'action' => $isQuarantined ? 'release' : 'quarantine',
                                    'quarantine_reason' => $batch->quarantine_reason ?? '',
                                ];
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $batch->batch_number }}</div>
                                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $batch->branch?->code }} - {{ $batch->branch?->name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $batch->medicine?->code }} - {{ $batch->medicine?->name }}</div>
                                    <div class="mt-1 text-xs text-gray-400">
                                        {{ $batch->medicine?->strength ?? '-' }} | {{ $batch->medicine?->base_unit ?? '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $batch->supplier?->code ?? '-' }} - {{ $batch->supplier?->name ?? ($batch->supplier_name ?? '-') }}</div>
                                    <div class="mt-1 text-xs text-gray-400">Received {{ $batch->received_at?->format('d M Y') ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>Tersedia {{ number_format((float) $batch->quantity_available, 2) }}</div>
                                    <div class="mt-1 text-xs text-gray-400">
                                        Receive {{ number_format((float) $batch->quantity_received, 2) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $batch->expired_at?->format('d M Y') ?? '-' }}</div>
                                    @if ($daysLeft !== null)
                                        <div class="mt-1 text-xs text-gray-400">
                                            @if ($isExpired)
                                                Lewat {{ abs($daysLeft) }} hari
                                            @elseif ($daysLeft === 0)
                                                Expire hari ini
                                            @else
                                                {{ $daysLeft }} hari lagi
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $expiryClasses }}">
                                            {{ $expiryLabel }}
                                        </span>
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $stockStatusClasses }}">
                                            {{ $stockStatusLabel }}
                                        </span>
                                    </div>
                                    @if ($batch->quarantine_reason)
                                        <div class="mt-2 text-xs text-gray-400">{{ $batch->quarantine_reason }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'] && ! $isQuarantined)
                                            <button
                                                type="button"
                                                title="Quarantine batch"
                                                data-payload='@json($actionPayload)' x-on:click='openAction(JSON.parse($el.dataset.payload))'
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-amber-200 text-amber-600 transition hover:bg-amber-50 dark:border-amber-500/20 dark:text-amber-300 dark:hover:bg-amber-500/10"
                                            >
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M12 8V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    <path d="M12 16H12.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    <path d="M10.29 3.86L1.82 18A2 2 0 0 0 3.53 21H20.47A2 2 0 0 0 22.18 18L13.71 3.86A2 2 0 0 0 10.29 3.86Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        @endif

                                        @if ($abilities['edit'] && $isQuarantined && ! $isExpired)
                                            <button
                                                type="button"
                                                title="Release batch"
                                                data-payload='@json($actionPayload)' x-on:click='openAction(JSON.parse($el.dataset.payload))'
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-green-200 text-green-600 transition hover:bg-green-50 dark:border-green-500/20 dark:text-green-300 dark:hover:bg-green-500/10"
                                            >
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M4 12L9 17L20 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Tidak ada batch yang cocok dengan filter monitoring.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                {{ $batches->links() }}
            </div>
        </section>

        <x-ui.modal show="expiryModalOpen" maxWidth="2xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="form.action === 'quarantine' ? 'Quarantine Batch' : 'Release Batch'"></h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Update status batch dari halaman monitoring expiry.
                    </p>
                </div>
                <button
                    type="button"
                    x-on:click="expiryModalOpen = false"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white"
                >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <form method="POST" x-bind:action="`${updateBase}/${form.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="expiry-monitoring-update">
                <input type="hidden" name="entity_id" x-bind:value="form.id">
                <input type="hidden" name="action" x-bind:value="form.action">

                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="font-medium text-gray-900 dark:text-white" x-text="form.batch_number"></div>
                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-300" x-text="form.medicine_label"></div>
                    <div class="mt-1 text-xs text-gray-400" x-text="`${form.branch_label} | ${form.supplier_label}`"></div>
                    <div class="mt-1 text-xs text-gray-400" x-text="`Expired at ${form.expired_at || '-'}`"></div>
                </div>

                <template x-if="form.action === 'quarantine'">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alasan quarantine</label>
                        <textarea
                            x-model="form.quarantine_reason"
                            name="quarantine_reason"
                            rows="3"
                            class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        ></textarea>
                    </div>
                </template>

                <template x-if="form.action === 'release'">
                    <div class="rounded-2xl border border-green-200 bg-green-50 p-4 text-sm text-green-700 dark:border-green-500/20 dark:bg-green-500/10 dark:text-green-300">
                        Batch akan dikembalikan ke stok aktif karena belum melewati tanggal expired.
                    </div>
                </template>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="expiryModalOpen = false">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="form.action === 'quarantine' ? 'Simpan Quarantine' : 'Release Batch'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection

