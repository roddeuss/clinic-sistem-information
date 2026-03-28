@php
    $modalOpen = $errors->any() && in_array(old('form_context'), ['reorder-create', 'reorder-update'], true);
    $modalMode = old('form_context') === 'reorder-update' ? 'update' : 'create';
    $supplierDefaults = collect(old('supplier_preferences', [
        ['supplier_id' => '', 'priority' => 10, 'is_primary' => true],
        ['supplier_id' => '', 'priority' => 20, 'is_primary' => false],
        ['supplier_id' => '', 'priority' => 30, 'is_primary' => false],
    ]))->map(fn ($row) => [
        'supplier_id' => (string) ($row['supplier_id'] ?? ''),
        'priority' => (string) ($row['priority'] ?? ''),
        'is_primary' => filter_var($row['is_primary'] ?? false, FILTER_VALIDATE_BOOL),
    ])->pad(3, ['supplier_id' => '', 'priority' => '', 'is_primary' => false])->take(3)->values()->all();
    $modalForm = [
        'id' => old('entity_id'),
        'medicine_id' => (string) old('medicine_id', $medicineOptions->first()?->id ?? ''),
        'branch_id' => (string) old('branch_id', $branchOptions->first()?->id ?? ''),
        'preferred_purchase_unit_id' => (string) old('preferred_purchase_unit_id', ''),
        'minimum_stock' => old('minimum_stock', 0),
        'safety_stock' => old('safety_stock', 0),
        'reorder_point' => old('reorder_point', 0),
        'reorder_quantity' => old('reorder_quantity', 0),
        'lead_time_days' => old('lead_time_days', 0),
        'notes' => old('notes', ''),
        'is_active' => (int) old('is_active', 1) === 1,
        'supplier_preferences' => $supplierDefaults,
    ];
    $medicinePayload = $medicineOptions->map(fn ($medicine) => [
        'id' => (string) $medicine->id,
        'label' => trim($medicine->code . ' - ' . $medicine->name),
        'units' => $medicine->units->map(fn ($unit) => [
            'id' => (string) $unit->id,
            'label' => $unit->label,
            'conversion_factor' => (float) $unit->conversion_factor,
        ])->values()->all(),
    ])->values()->all();
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Reorder Points" />

    <div x-data="{
        modalOpen: @js($modalOpen),
        modalMode: @js($modalMode),
        storeAction: @js(route('reorder-points.store')),
        updateBase: @js(url('/reorder-points')),
        medicines: @js($medicinePayload),
        form: @js($modalForm),
        baseBranchId: @js((string) ($branchOptions->first()?->id ?? '')),
        parsePayload(payload) {
            try {
                return JSON.parse(payload || '{}');
            } catch (error) {
                return {};
            }
        },
        emptySuppliers() { return [{ supplier_id:'', priority:'10', is_primary:true }, { supplier_id:'', priority:'20', is_primary:false }, { supplier_id:'', priority:'30', is_primary:false }]; },
        emptyForm() { return { id:null, medicine_id:this.medicines[0]?.id ?? '', branch_id:this.baseBranchId, preferred_purchase_unit_id:'', minimum_stock:0, safety_stock:0, reorder_point:0, reorder_quantity:0, lead_time_days:0, notes:'', is_active:true, supplier_preferences:this.emptySuppliers() }; },
        openCreate() { this.modalMode = 'create'; this.form = this.emptyForm(); this.modalOpen = true; },
        openEdit(payload) { const parsed = this.parsePayload(payload); this.modalMode = 'update'; this.form = { ...this.emptyForm(), ...parsed, supplier_preferences:[...this.emptySuppliers(), ...(parsed.supplier_preferences || [])].slice(0, 3) }; this.modalOpen = true; },
        availableUnits() { return this.medicines.find((row) => row.id === `${this.form.medicine_id}`)?.units ?? []; },
        markPrimary(index) { this.form.supplier_preferences = this.form.supplier_preferences.map((row, rowIndex) => ({ ...row, is_primary: rowIndex === index })); },
    }" @keydown.escape.window="modalOpen = false" class="space-y-6">
        @if (session('status'))
            <x-ui.alert variant="success" :message="session('status')" />
        @endif
        @if ($errors->any())
            <x-ui.alert variant="error" :message="$errors->first()" />
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Reorder Points</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">Threshold disimpan per branch dan medicine. Monitoring memakai stok aktif base unit, lalu sistem menampilkan rekomendasi order berdasarkan purchase UOM dan supplier prioritas.</p>
                </div>
                @if ($abilities['create'])
                    <x-ui.button type="button" x-on:click="openCreate()">Tambah Policy</x-ui.button>
                @endif
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900"><div class="text-sm text-gray-500 dark:text-gray-400">Active policies</div><div class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ number_format($reorderMetrics['active_policies']) }}</div></div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900"><div class="text-sm text-gray-500 dark:text-gray-400">Branches covered</div><div class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ number_format($reorderMetrics['branches_covered']) }}</div></div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900"><div class="text-sm text-gray-500 dark:text-gray-400">Low stock items</div><div class="mt-2 text-3xl font-semibold text-amber-600 dark:text-amber-300">{{ number_format($reorderMetrics['low_stock_items']) }}</div></div>
        </section>

        @if ($lowStockRecommendations->isNotEmpty())
            <section class="rounded-2xl border border-amber-200 bg-amber-50/60 p-6 shadow-theme-sm dark:border-amber-500/20 dark:bg-amber-500/10">
                <h2 class="text-lg font-semibold text-amber-900 dark:text-amber-100">Low stock recommendations</h2>
                <div class="mt-4 grid gap-4 xl:grid-cols-2">
                    @foreach ($lowStockRecommendations as $recommendation)
                        <div class="rounded-2xl border border-amber-200 bg-white px-4 py-4 dark:border-amber-500/20 dark:bg-gray-900">
                            <div class="font-medium text-gray-900 dark:text-white">{{ $recommendation['medicine']?->name }}</div>
                            <div class="mt-1 text-xs text-gray-400">{{ $recommendation['medicine']?->code }} | {{ $recommendation['branch']?->code }}</div>
                            <div class="mt-3 text-sm text-gray-600 dark:text-gray-300">Available {{ number_format((float) $recommendation['available_quantity'], 2) }} {{ $recommendation['medicine']?->base_unit }}</div>
                            <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">Recommended {{ number_format((float) $recommendation['recommended_purchase_quantity'], 2) }} {{ $recommendation['purchase_unit_label'] }}</div>
                            <div class="mt-1 text-xs text-gray-400">Suppliers: {{ collect($recommendation['suppliers'])->pluck('name')->filter()->implode(', ') ?: '-' }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('reorder-points') }}" class="grid gap-4 md:grid-cols-[1fr_220px_180px_auto]">
                <input type="text" name="search" value="{{ $reorderFilters['search'] }}" placeholder="Cari medicine, branch, atau supplier" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <select name="branch" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua branch</option>@foreach ($branchOptions as $branch)<option value="{{ $branch->id }}" @selected($reorderFilters['branch'] === (string) $branch->id)>{{ $branch->code }} - {{ $branch->name }}</option>@endforeach</select>
                <select name="status" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua status</option><option value="active" @selected($reorderFilters['status'] === 'active')>Active</option><option value="inactive" @selected($reorderFilters['status'] === 'inactive')>Inactive</option></select>
                <div class="flex gap-3"><x-ui.button type="submit" variant="outline">Filter</x-ui.button><a href="{{ route('reorder-points') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a></div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Policy list</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $reorderPolicies->firstItem() ?? 0 }} - {{ $reorderPolicies->lastItem() ?? 0 }} dari {{ $reorderPolicies->total() }} policy.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Medicine</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Policy</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Suppliers</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Live Stock</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Recommendation</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th><th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($reorderPolicies as $policy)
                            @php
                                $computed = $policy->getAttribute('computed_reorder');
                                $payload = [
                                    'id' => $policy->id,
                                    'medicine_id' => (string) $policy->medicine_id,
                                    'branch_id' => (string) $policy->branch_id,
                                    'preferred_purchase_unit_id' => (string) ($policy->preferred_purchase_unit_id ?? ''),
                                    'minimum_stock' => (string) $policy->minimum_stock,
                                    'safety_stock' => (string) $policy->safety_stock,
                                    'reorder_point' => (string) $policy->reorder_point,
                                    'reorder_quantity' => (string) $policy->reorder_quantity,
                                    'lead_time_days' => (string) $policy->lead_time_days,
                                    'notes' => $policy->notes ?? '',
                                    'is_active' => $policy->is_active,
                                    'supplier_preferences' => $policy->supplierPreferences->map(fn ($link) => ['supplier_id' => (string) $link->supplier_id, 'priority' => (string) $link->priority, 'is_primary' => (bool) $link->is_primary])->values()->all(),
                                ];
                                $payloadJson = e(json_encode($payload));
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4"><div class="font-medium text-gray-900 dark:text-white">{{ $policy->medicine?->name }}</div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $policy->medicine?->code }} | {{ $policy->branch?->code }}</div><div class="mt-1 text-xs text-gray-400">Base {{ $policy->medicine?->base_unit }}</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><div>Min {{ number_format((float) $policy->minimum_stock, 2) }}</div><div class="mt-1">Safety {{ number_format((float) $policy->safety_stock, 2) }}</div><div class="mt-1">ROP {{ number_format((float) $policy->reorder_point, 2) }}</div><div class="mt-1">ROQ {{ number_format((float) $policy->reorder_quantity, 2) }}</div><div class="mt-1 text-xs text-gray-400">Lead {{ $policy->lead_time_days }} hari</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">@forelse ($policy->supplierPreferences as $link)<div>{{ $link->priority }}. {{ $link->supplier?->name ?? '-' }} @if($link->is_primary)<span class="text-xs text-brand-600 dark:text-brand-300">(primary)</span>@endif</div>@empty<div>-</div>@endforelse</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><div class="font-medium text-gray-900 dark:text-white">{{ number_format((float) $computed['available_quantity'], 2) }} {{ $policy->medicine?->base_unit }}</div><div class="mt-1 text-xs text-gray-400">Ratio {{ number_format((float) $computed['stock_ratio'] * 100, 1) }}%</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><div class="font-medium text-gray-900 dark:text-white">{{ number_format((float) $computed['recommended_purchase_quantity'], 2) }} {{ $computed['purchase_unit_label'] }}</div><div class="mt-1 text-xs text-gray-400">{{ number_format((float) $computed['recommended_order_quantity'], 2) }} {{ $policy->medicine?->base_unit }} base</div></td>
                                <td class="px-6 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $computed['is_low_stock'] ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' : 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300' }}">{{ $computed['is_low_stock'] ? 'Low stock' : ($policy->is_active ? 'Healthy' : 'Inactive') }}</span></td>
                                <td class="px-6 py-4"><div class="flex justify-end gap-2">@if ($abilities['edit'])<x-ui.icon-button title="Edit policy" data-payload="{{ $payloadJson }}" x-on:click="openEdit($event.currentTarget.dataset.payload)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button>@endif @if ($abilities['delete'])<form method="POST" action="{{ route('reorder-points.delete', $policy) }}" onsubmit="return confirm('Arsipkan reorder point policy ini?')">@csrf<x-ui.icon-button type="submit" variant="danger" title="Arsipkan policy"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></form>@endif</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada reorder point policy.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $reorderPolicies->links() }}</div>
        </section>

        <x-ui.modal show="modalOpen" maxWidth="4xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="modalMode === 'create' ? 'Tambah Reorder Point Policy' : 'Update Reorder Point Policy'"></h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Threshold disimpan di base unit. Supplier pertama dianggap primary.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="modalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div>
            <form method="POST" x-bind:action="modalMode === 'create' ? storeAction : `${updateBase}/${form.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="modalMode === 'create' ? 'reorder-create' : 'reorder-update'">
                <input type="hidden" name="entity_id" x-bind:value="form.id">
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Medicine</label><select x-model="form.medicine_id" name="medicine_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Pilih medicine</option>@foreach ($medicineOptions as $medicine)<option value="{{ $medicine->id }}">{{ $medicine->code }} - {{ $medicine->name }}</option>@endforeach</select></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Branch</label><select x-model="form.branch_id" name="branch_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">@foreach ($branchOptions as $branch)<option value="{{ $branch->id }}">{{ $branch->code }} - {{ $branch->name }}</option>@endforeach</select></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Preferred purchase UOM</label><select x-model="form.preferred_purchase_unit_id" name="preferred_purchase_unit_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Auto by medicine</option><template x-for="unit in availableUnits()" :key="unit.id"><option :value="unit.id" x-text="`${unit.label} (x${unit.conversion_factor})`"></option></template></select></div>
                    <div class="flex items-end"><div><input type="hidden" name="is_active" x-bind:value="form.is_active ? 1 : 0"><label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300"><input x-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20"><span>Active policy</span></label></div></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Minimum stock</label><input x-model="form.minimum_stock" type="number" step="0.01" min="0" name="minimum_stock" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Safety stock</label><input x-model="form.safety_stock" type="number" step="0.01" min="0" name="safety_stock" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Reorder point</label><input x-model="form.reorder_point" type="number" step="0.01" min="0.01" name="reorder_point" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Reorder quantity</label><input x-model="form.reorder_quantity" type="number" step="0.01" min="0.01" name="reorder_quantity" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Lead time (days)</label><input x-model="form.lead_time_days" type="number" min="0" max="365" name="lead_time_days" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                </div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label><textarea x-model="form.notes" name="notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                <div><div class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Supplier priority</div><div class="space-y-3"><template x-for="(row, index) in form.supplier_preferences" :key="index"><div class="grid gap-4 md:grid-cols-[1fr_120px_100px]"><select x-model="row.supplier_id" :name="`supplier_preferences[${index}][supplier_id]`" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Pilih supplier</option>@foreach ($supplierOptions as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->code }} - {{ $supplier->name }}</option>@endforeach</select><input x-model="row.priority" :name="`supplier_preferences[${index}][priority]`" type="number" min="1" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><div class="flex items-center gap-3 rounded-xl border border-gray-200 px-4 dark:border-gray-700"><input type="hidden" :name="`supplier_preferences[${index}][is_primary]`" :value="row.is_primary ? 1 : 0"><input x-model="row.is_primary" @change="markPrimary(index)" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20"><span class="text-sm text-gray-700 dark:text-gray-300">Primary</span></div></div></template></div></div>
                <div class="flex justify-end gap-3"><x-ui.button type="button" variant="outline" x-on:click="modalOpen = false">Batal</x-ui.button><x-ui.button type="submit" x-text="modalMode === 'create' ? 'Simpan Policy' : 'Update Policy'"></x-ui.button></div>
            </form>
        </x-ui.modal>
    </div>
@endsection
