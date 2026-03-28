@php
    $defaultMedicineId = (string) ($medicineOptions->first()?->id ?? '');
    $defaultBranchId = (string) ($branchOptions->first()?->id ?? '');
    $medicineModalOpen = $errors->any() && in_array(old('form_context'), ['medicine-create', 'medicine-update'], true);
    $medicineModalMode = old('form_context') === 'medicine-update' ? 'update' : 'create';
    $batchModalOpen = $errors->any() && in_array(old('form_context'), ['batch-create', 'batch-update'], true);
    $batchModalMode = old('form_context') === 'batch-update' ? 'update' : 'create';
    $oldUoms = collect(old('uoms', []))
        ->map(fn ($row, $index) => [
            'id' => $row['id'] ?? null,
            'label' => $row['label'] ?? '',
            'conversion_factor' => $row['conversion_factor'] ?? ($index === 0 ? '1' : ''),
            'allow_purchase' => (int) ($row['allow_purchase'] ?? ($index === 0 ? 1 : 0)) === 1,
            'allow_dispense' => (int) ($row['allow_dispense'] ?? ($index === 0 ? 1 : 0)) === 1,
            'is_base' => (int) ($row['is_base'] ?? ($index === 0 ? 1 : 0)) === 1,
            'sort_order' => $row['sort_order'] ?? (($index + 1) * 10),
        ])
        ->values()
        ->all();
    $medicineModalForm = [
        'id' => old('entity_id'),
        'code' => old('code', ''),
        'product_category_id' => (string) old('product_category_id', ''),
        'name' => old('name', ''),
        'generic_name' => old('generic_name', ''),
        'active_ingredients' => old('active_ingredients', ''),
        'dosage_form' => old('dosage_form', 'tablet'),
        'therapeutic_class' => old('therapeutic_class', ''),
        'strength' => old('strength', ''),
        'base_unit' => old('base_unit', ''),
        'description' => old('description', ''),
        'contraindication_notes' => old('contraindication_notes', ''),
        'allergy_keywords' => old('allergy_keywords', ''),
        'uoms' => $oldUoms !== [] ? $oldUoms : [[
            'id' => null,
            'label' => strtoupper((string) old('base_unit', 'TABLET')),
            'conversion_factor' => '1',
            'allow_purchase' => true,
            'allow_dispense' => true,
            'is_base' => true,
            'sort_order' => 10,
        ]],
        'branch_prices' => $branchOptions->mapWithKeys(fn ($branch) => [(string) $branch->id => old("branch_prices.{$branch->id}", '')])->all(),
        'is_compoundable' => (int) old('is_compoundable', 0) === 1,
        'is_active' => (int) old('is_active', 1) === 1,
    ];
    $batchModalForm = [
        'id' => old('entity_id'),
        'medicine_id' => (string) old('medicine_id', $defaultMedicineId),
        'branch_id' => (string) old('branch_id', $defaultBranchId),
        'batch_number' => old('batch_number', ''),
        'received_at' => old('received_at', now()->toDateString()),
        'expired_at' => old('expired_at', ''),
        'quantity_received' => old('quantity_received', ''),
        'quantity_available' => old('quantity_available', ''),
        'purchase_cost' => old('purchase_cost', ''),
        'supplier_id' => (string) old('supplier_id', ''),
        'supplier_name' => old('supplier_name', ''),
        'notes' => old('notes', ''),
        'is_active' => (int) old('is_active', 1) === 1,
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Pharmacy" />

    <div x-data="{
        medicineModalOpen: @js($medicineModalOpen),
        batchModalOpen: @js($batchModalOpen),
        medicineMode: @js($medicineModalMode),
        batchMode: @js($batchModalMode),
        medicineStoreAction: @js(route('pharmacy.store')),
        medicineUpdateBase: @js(url('/pharmacy/medicines')),
        batchStoreAction: @js(route('medicine-batches.store')),
        batchUpdateBase: @js(url('/pharmacy/batches')),
        medicineForm: @js($medicineModalForm),
        batchForm: @js($batchModalForm),
        emptyUom(index = 0) { return { id:null, label:index === 0 ? 'TABLET' : '', conversion_factor:index === 0 ? '1' : '', allow_purchase:index === 0, allow_dispense:index === 0, is_base:index === 0, sort_order:(index + 1) * 10 }; },
        emptyMedicine() { return { id:null, code:'', product_category_id:'', name:'', generic_name:'', active_ingredients:'', dosage_form:'tablet', therapeutic_class:'', strength:'', base_unit:'TABLET', description:'', contraindication_notes:'', allergy_keywords:'', uoms:[this.emptyUom(0)], branch_prices:@js($branchOptions->mapWithKeys(fn ($branch) => [(string) $branch->id => ''])->all()), is_compoundable:false, is_active:true }; },
        emptyBatch() { return { id:null, medicine_id:@js($defaultMedicineId), branch_id:@js($defaultBranchId), batch_number:'', received_at:@js(now()->toDateString()), expired_at:'', quantity_received:'', quantity_available:'', purchase_cost:'', supplier_id:'', supplier_name:'', notes:'', is_active:true }; },
        normalizeBaseUnit() {
            const base = (this.medicineForm.uoms || []).find(unit => unit.is_base);
            this.medicineForm.base_unit = base?.label || '';
        },
        addUom() {
            const nextIndex = (this.medicineForm.uoms || []).length;
            this.medicineForm.uoms.push(this.emptyUom(nextIndex));
        },
        removeUom(index) {
            if ((this.medicineForm.uoms || []).length === 1) {
                this.medicineForm.uoms = [this.emptyUom(0)];
                this.normalizeBaseUnit();
                return;
            }
            this.medicineForm.uoms.splice(index, 1);
            if (!this.medicineForm.uoms.some(unit => unit.is_base)) {
                this.medicineForm.uoms[0].is_base = true;
                this.medicineForm.uoms[0].conversion_factor = '1';
            }
            this.normalizeBaseUnit();
        },
        setBaseUom(index) {
            this.medicineForm.uoms = (this.medicineForm.uoms || []).map((unit, unitIndex) => ({
                ...unit,
                is_base: unitIndex === index,
                conversion_factor: unitIndex === index ? '1' : unit.conversion_factor,
            }));
            this.normalizeBaseUnit();
        },
        openCreateMedicine() { this.medicineMode = 'create'; this.medicineForm = this.emptyMedicine(); this.medicineModalOpen = true; },
        openEditMedicine(payload) { this.medicineMode = 'update'; this.medicineForm = payload; this.normalizeBaseUnit(); this.medicineModalOpen = true; },
        openCreateBatch() { this.batchMode = 'create'; this.batchForm = this.emptyBatch(); this.batchModalOpen = true; },
        openEditBatch(payload) { this.batchMode = 'update'; this.batchForm = payload; this.batchModalOpen = true; },
    }" @keydown.escape.window="medicineModalOpen = false; batchModalOpen = false" class="space-y-6">
        @if (session('status'))
            <x-ui.alert variant="success" :message="session('status')" />
        @endif
        @if ($errors->any())
            <x-ui.alert variant="error" :message="$errors->first()" />
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Pharmacy foundation</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">Master obat, harga per branch, batch FEFO, dan expired tracking untuk dispensing in-house dan racikan kapsul.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    @if ($abilities['create'])
                        <x-ui.button type="button" x-on:click="openCreateMedicine()">Tambah Medicine</x-ui.button>
                        <button type="button" x-on:click="openCreateBatch()" @disabled($medicineOptions->isEmpty() || $branchOptions->isEmpty()) class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">Tambah Batch</button>
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('pharmacy') }}" class="grid gap-4 md:grid-cols-[1.2fr_220px_180px_180px_auto]">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari medicine atau batch" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <select name="branch" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua branch</option>@foreach ($branchOptions as $branchOption)<option value="{{ $branchOption->id }}" @selected($filters['branch'] === (string) $branchOption->id)>{{ $branchOption->code }} - {{ $branchOption->name }}</option>@endforeach</select>
                <select name="status" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua status</option><option value="active" @selected($filters['status'] === 'active')>Active</option><option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option></select>
                <select name="batch_status" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua batch</option><option value="available" @selected($filters['batch_status'] === 'available')>Available</option><option value="expired" @selected($filters['batch_status'] === 'expired')>Expired</option><option value="empty" @selected($filters['batch_status'] === 'empty')>Empty</option></select>
                <div class="flex gap-3"><x-ui.button type="submit" variant="outline">Filter</x-ui.button><a href="{{ route('pharmacy') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a></div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Medicine master</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $medicines->firstItem() ?? 0 }} - {{ $medicines->lastItem() ?? 0 }} dari {{ $medicines->total() }} medicine.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Medicine</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Pricing</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Inventory</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th><th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($medicines as $medicine)
                            @php
                                $medicinePayload = [
                                    'id' => $medicine->id,
                                    'code' => $medicine->code,
                                    'product_category_id' => (string) ($medicine->product_category_id ?? ''),
                                    'name' => $medicine->name,
                                    'generic_name' => $medicine->generic_name ?? '',
                                    'active_ingredients' => $medicine->active_ingredients ?? '',
                                    'dosage_form' => $medicine->dosage_form,
                                    'therapeutic_class' => $medicine->therapeutic_class ?? '',
                                    'strength' => $medicine->strength ?? '',
                                    'base_unit' => $medicine->base_unit ?? '',
                                    'description' => $medicine->description ?? '',
                                    'contraindication_notes' => $medicine->contraindication_notes ?? '',
                                    'allergy_keywords' => $medicine->allergy_keywords ?? '',
                                    'uoms' => $medicine->units->map(fn ($unit) => [
                                        'id' => $unit->id,
                                        'label' => $unit->label,
                                        'conversion_factor' => (string) $unit->conversion_factor,
                                        'allow_purchase' => $unit->allow_purchase,
                                        'allow_dispense' => $unit->allow_dispense,
                                        'is_base' => $unit->is_base,
                                        'sort_order' => $unit->sort_order,
                                    ])->values()->all(),
                                    'branch_prices' => $branchOptions->mapWithKeys(fn ($branch) => [(string) $branch->id => (string) ($medicine->branchPrices->firstWhere('branch_id', $branch->id)?->selling_price ?? '')])->all(),
                                    'is_compoundable' => $medicine->is_compoundable,
                                    'is_active' => $medicine->is_active,
                                ];
                                $nextExpired = $medicine->batches->where('quantity_available', '>', 0)->filter(fn ($batch) => $batch->expired_at)->sortBy('expired_at')->first();
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4"><div class="font-medium text-gray-900 dark:text-white">{{ $medicine->name }}</div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $medicine->code }}{{ $medicine->strength ? ' | ' . $medicine->strength : '' }}</div><div class="mt-1 text-xs text-gray-400">{{ ucfirst($medicine->dosage_form) }}{{ $medicine->base_unit ? ' | ' . $medicine->base_unit : '' }}</div>@if($medicine->units->isNotEmpty())<div class="mt-2 text-xs text-gray-400">UOM: {{ $medicine->units->map(fn ($unit) => $unit->label . ' x' . number_format((float) $unit->conversion_factor, 0, ',', '.'))->implode(' | ') }}</div>@endif @if($medicine->productCategory)<div class="mt-1 text-xs text-brand-600 dark:text-brand-300">{{ $medicine->productCategory->name }}</div>@endif @if($medicine->generic_name)<div class="mt-1 text-xs text-gray-400">{{ $medicine->generic_name }}</div>@endif @if($medicine->therapeutic_class)<div class="mt-1 text-xs text-gray-400">Class: {{ $medicine->therapeutic_class }}</div>@endif @if($medicine->active_ingredients)<div class="mt-1 text-xs text-gray-400">Ingredients: {{ $medicine->active_ingredients }}</div>@endif</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">@foreach ($branchOptions as $branchOption) @php $price = $medicine->branchPrices->firstWhere('branch_id', $branchOption->id); @endphp <div class="flex items-center justify-between gap-3"><span>{{ $branchOption->code }}</span><span>{{ $price ? 'Rp ' . number_format((float) $price->selling_price, 0, ',', '.') : '-' }}</span></div> @endforeach</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><div>{{ number_format((float) $medicine->batches->sum('quantity_available'), 2) }} {{ $medicine->base_unit ?: '' }}</div><div class="mt-1 text-xs text-gray-400">{{ $medicine->batches->count() }} batch</div>@if($nextExpired)<div class="mt-2 text-xs text-amber-600 dark:text-amber-300">Expired terdekat {{ $nextExpired->batch_number }} - {{ $nextExpired->expired_at?->format('d M Y') }}</div>@endif</td>
                                <td class="px-6 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $medicine->is_active ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300' }}">{{ $medicine->is_active ? 'Active' : 'Inactive' }}</span>@if($medicine->is_compoundable)<div class="mt-2"><span class="inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">Compoundable</span></div>@endif</td>
                                <td class="px-6 py-4"><div class="flex justify-end gap-2">@if ($abilities['edit'])<x-ui.icon-button title="Edit medicine" data-payload='@json($medicinePayload)' x-on:click='openEditMedicine(JSON.parse($el.dataset.payload))'><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button>@endif @if ($abilities['delete'])<form method="POST" action="{{ route('pharmacy.delete', $medicine) }}" onsubmit="return confirm('Hapus medicine ini?')">@csrf<x-ui.icon-button type="submit" variant="danger" title="Hapus medicine"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></form>@endif</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada medicine yang cocok dengan filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $medicines->links() }}</div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Batch inventory</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $batches->firstItem() ?? 0 }} - {{ $batches->lastItem() ?? 0 }} dari {{ $batches->total() }} batch.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Batch</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Branch</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Date</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Quantity</th><th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($batches as $batch)
                            @php
                                $batchPayload = [
                                    'id' => $batch->id,
                                    'medicine_id' => (string) $batch->medicine_id,
                                    'branch_id' => (string) $batch->branch_id,
                                    'batch_number' => $batch->batch_number,
                                    'received_at' => $batch->received_at?->format('Y-m-d'),
                                    'expired_at' => $batch->expired_at?->format('Y-m-d') ?? '',
                                    'quantity_received' => (string) $batch->quantity_received,
                                    'quantity_available' => (string) $batch->quantity_available,
                                    'purchase_cost' => (string) ($batch->purchase_cost ?? ''),
                                    'supplier_id' => (string) ($batch->supplier_id ?? ''),
                                    'supplier_name' => $batch->supplier_name ?? '',
                                    'notes' => $batch->notes ?? '',
                                    'is_active' => $batch->is_active,
                                ];
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4"><div class="font-medium text-gray-900 dark:text-white">{{ $batch->medicine?->name ?? '-' }}</div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $batch->batch_number }}</div><div class="mt-1 text-xs text-gray-400">{{ $batch->medicine?->code }}</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $batch->branch?->code }} - {{ $batch->branch?->name }}</div>
                                    @if ($batch->supplier || $batch->supplier_name)
                                        <div class="mt-1 text-xs text-gray-400">
                                            {{ $batch->supplier?->name ?? $batch->supplier_name }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><div>Received {{ $batch->received_at?->format('d M Y') }}</div><div class="mt-1 {{ $batch->expired_at && $batch->expired_at->isPast() ? 'text-red-600 dark:text-red-300' : 'text-gray-400' }}">{{ $batch->expired_at ? 'Expired ' . $batch->expired_at->format('d M Y') : 'No expiry date' }}</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><div>{{ number_format((float) $batch->quantity_available, 2) }} tersedia</div><div class="mt-1 text-xs text-gray-400">Dari {{ number_format((float) $batch->quantity_received, 2) }}</div>@if($batch->purchase_cost)<div class="mt-1 text-xs text-gray-400">HPP Rp {{ number_format((float) $batch->purchase_cost, 0, ',', '.') }}</div>@endif</td>
                                <td class="px-6 py-4"><div class="flex justify-end gap-2">@if ($abilities['edit'])<x-ui.icon-button title="Edit batch" data-payload='@json($batchPayload)' x-on:click='openEditBatch(JSON.parse($el.dataset.payload))'><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button>@endif @if ($abilities['delete'])<form method="POST" action="{{ route('medicine-batches.delete', $batch) }}" onsubmit="return confirm('Hapus batch ini?')">@csrf<x-ui.icon-button type="submit" variant="danger" title="Hapus batch"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></form>@endif</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada batch yang cocok dengan filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $batches->links() }}</div>
        </section>

        <x-ui.modal show="medicineModalOpen" maxWidth="4xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800 flex items-start justify-between gap-4"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="medicineMode === 'create' ? 'Tambah Medicine' : 'Update Medicine'"></h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Harga branch boleh kosong jika item belum dijual di branch tersebut.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="medicineModalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div>
            <form method="POST" x-bind:action="medicineMode === 'create' ? medicineStoreAction : `${medicineUpdateBase}/${medicineForm.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="medicineMode === 'create' ? 'medicine-create' : 'medicine-update'">
                <input type="hidden" name="entity_id" x-bind:value="medicineForm.id">
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Code</label><input x-model="medicineForm.code" type="text" name="code" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label><select x-model="medicineForm.product_category_id" name="product_category_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Tanpa category</option>@foreach ($categoryOptions as $categoryOption)<option value="{{ $categoryOption->id }}">{{ $categoryOption->code }} - {{ $categoryOption->name }}</option>@endforeach</select></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Medicine name</label><input x-model="medicineForm.name" type="text" name="name" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Generic name</label><input x-model="medicineForm.generic_name" type="text" name="generic_name" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Active ingredients</label><textarea x-model="medicineForm.active_ingredients" name="active_ingredients" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Dosage form</label><input x-model="medicineForm.dosage_form" type="text" name="dosage_form" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Therapeutic class</label><input x-model="medicineForm.therapeutic_class" type="text" name="therapeutic_class" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Strength</label><input x-model="medicineForm.strength" type="text" name="strength" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Base unit</label><input x-model="medicineForm.base_unit" type="text" name="base_unit" readonly class="h-11 w-full rounded-xl border border-gray-300 bg-gray-50 px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-white"></div>
                </div>
                <div class="rounded-2xl border border-gray-200 dark:border-gray-800">
                    <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white">UOM levels</h4>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Stok selalu disimpan di base unit. Unit lain seperti box atau strip akan otomatis dikonversi ke base unit.</p>
                        </div>
                        <x-ui.button type="button" variant="outline" x-on:click="addUom()">Tambah UOM</x-ui.button>
                    </div>
                    <div class="space-y-4 p-5">
                        <template x-for="(uom, index) in medicineForm.uoms" :key="`uom-${index}-${uom.id ?? 'new'}`">
                            <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                                <input type="hidden" x-bind:name="`uoms[${index}][id]`" x-bind:value="uom.id">
                                <input type="hidden" x-bind:name="`uoms[${index}][allow_purchase]`" x-bind:value="uom.allow_purchase ? 1 : 0">
                                <input type="hidden" x-bind:name="`uoms[${index}][allow_dispense]`" x-bind:value="uom.allow_dispense ? 1 : 0">
                                <input type="hidden" x-bind:name="`uoms[${index}][is_base]`" x-bind:value="uom.is_base ? 1 : 0">
                                <input type="hidden" x-bind:name="`uoms[${index}][sort_order]`" x-bind:value="uom.sort_order">
                                <div class="grid gap-4 md:grid-cols-[1.1fr_180px_160px_160px_auto]">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Unit label</label>
                                        <input x-model="uom.label" x-on:input="uom.label = uom.label.toUpperCase(); if (uom.is_base) normalizeBaseUnit()" x-bind:name="`uoms[${index}][label]`" type="text" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Factor to base</label>
                                        <input x-model="uom.conversion_factor" x-bind:readonly="uom.is_base" x-bind:name="`uoms[${index}][conversion_factor]`" type="number" step="0.0001" min="0.0001" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 read-only:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:read-only:bg-gray-800">
                                    </div>
                                    <div class="flex items-end">
                                        <label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                            <input x-model="uom.allow_purchase" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                                            <span>Purchase</span>
                                        </label>
                                    </div>
                                    <div class="flex items-end">
                                        <label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                            <input x-model="uom.allow_dispense" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                                            <span>Dispense</span>
                                        </label>
                                    </div>
                                    <div class="flex items-end justify-end gap-2">
                                        <button type="button" x-on:click="setBaseUom(index)" x-bind:class="uom.is_base ? 'border-emerald-200 text-emerald-700 bg-emerald-50 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300' : 'border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-300'" class="inline-flex h-10 items-center rounded-xl border px-3 text-sm font-medium">
                                            <span x-text="uom.is_base ? 'Base unit' : 'Set as base'"></span>
                                        </button>
                                        <x-ui.icon-button type="button" title="Hapus UOM" x-on:click="removeUom(index)">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                        </x-ui.icon-button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label><textarea x-model="medicineForm.description" name="description" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Allergy keywords</label><textarea x-model="medicineForm.allergy_keywords" name="allergy_keywords" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Contraindication notes</label><textarea x-model="medicineForm.contraindication_notes" name="contraindication_notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                </div>
                <div class="grid gap-4 md:grid-cols-2">@foreach ($branchOptions as $branchOption)<div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $branchOption->code }} price</label><input x-model="medicineForm.branch_prices['{{ $branchOption->id }}']" type="number" step="0.01" min="0" name="branch_prices[{{ $branchOption->id }}]" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>@endforeach</div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div><input type="hidden" name="is_compoundable" x-bind:value="medicineForm.is_compoundable ? 1 : 0"><label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300"><input x-model="medicineForm.is_compoundable" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20"><span>Compoundable</span></label></div>
                    <div><input type="hidden" name="is_active" x-bind:value="medicineForm.is_active ? 1 : 0"><label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300"><input x-model="medicineForm.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20"><span>Active</span></label></div>
                </div>
                <div class="flex justify-end gap-3"><x-ui.button type="button" variant="outline" x-on:click="medicineModalOpen = false">Batal</x-ui.button><x-ui.button type="submit" x-text="medicineMode === 'create' ? 'Simpan Medicine' : 'Update Medicine'"></x-ui.button></div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="batchModalOpen" maxWidth="3xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800 flex items-start justify-between gap-4"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="batchMode === 'create' ? 'Tambah Batch' : 'Update Batch'"></h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Batch dipakai untuk FEFO, expiry monitor, dan costing saat dispense.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="batchModalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div>
            <form method="POST" x-bind:action="batchMode === 'create' ? batchStoreAction : `${batchUpdateBase}/${batchForm.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="batchMode === 'create' ? 'batch-create' : 'batch-update'">
                <input type="hidden" name="entity_id" x-bind:value="batchForm.id">
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Medicine</label><select x-model="batchForm.medicine_id" name="medicine_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">@foreach ($medicineOptions as $medicineOption)<option value="{{ $medicineOption->id }}">{{ $medicineOption->code }} - {{ $medicineOption->name }}</option>@endforeach</select></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Branch</label><select x-model="batchForm.branch_id" name="branch_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">@foreach ($branchOptions as $branchOption)<option value="{{ $branchOption->id }}">{{ $branchOption->code }} - {{ $branchOption->name }}</option>@endforeach</select></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Batch number</label><input x-model="batchForm.batch_number" type="text" name="batch_number" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Supplier</label><select x-model="batchForm.supplier_id" name="supplier_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Tanpa supplier</option>@foreach ($supplierOptions as $supplierOption)<option value="{{ $supplierOption->id }}">{{ $supplierOption->code }} - {{ $supplierOption->name }}</option>@endforeach</select><input type="hidden" name="supplier_name" x-bind:value="batchForm.supplier_name"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Received at</label><input x-model="batchForm.received_at" type="date" name="received_at" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Expired at</label><input x-model="batchForm.expired_at" type="date" name="expired_at" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity received</label><input x-model="batchForm.quantity_received" type="number" step="0.01" min="0" name="quantity_received" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity available</label><input x-model="batchForm.quantity_available" type="number" step="0.01" min="0" name="quantity_available" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Purchase cost</label><input x-model="batchForm.purchase_cost" type="number" step="0.01" min="0" name="purchase_cost" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div class="flex items-end"><div><input type="hidden" name="is_active" x-bind:value="batchForm.is_active ? 1 : 0"><label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300"><input x-model="batchForm.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20"><span>Batch aktif</span></label></div></div>
                </div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label><textarea x-model="batchForm.notes" name="notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                <div class="flex justify-end gap-3"><x-ui.button type="button" variant="outline" x-on:click="batchModalOpen = false">Batal</x-ui.button><x-ui.button type="submit" x-text="batchMode === 'create' ? 'Simpan Batch' : 'Update Batch'"></x-ui.button></div>
            </form>
        </x-ui.modal>
    </div>
@endsection

