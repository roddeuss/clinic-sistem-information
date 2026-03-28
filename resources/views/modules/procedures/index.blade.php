@php
    $defaultVisitId = (string) ($visitOptions->first()?->id ?? '');
    $defaultMasterId = (string) ($masterOptions->first()?->id ?? '');
    $masterModalOpen = $errors->any() && in_array(old('form_context'), ['procedure-master-create', 'procedure-master-update'], true);
    $masterModalMode = old('form_context') === 'procedure-master-update' ? 'update' : 'create';
    $orderModalOpen = $errors->any() && in_array(old('form_context'), ['procedure-order-create', 'procedure-order-update'], true);
    $orderModalMode = old('form_context') === 'procedure-order-update' ? 'update' : 'create';
    $masterModalForm = [
        'id' => old('entity_id'),
        'code' => old('code', ''),
        'name' => old('name', ''),
        'description' => old('description', ''),
        'performer_scope' => old('performer_scope', 'both'),
        'default_fee' => old('default_fee', ''),
        'branch_prices' => $branchOptions->mapWithKeys(fn ($branch) => [(string) $branch->id => old("branch_prices.{$branch->id}", '')])->all(),
        'requires_doctor_order' => (int) old('requires_doctor_order', 1) === 1,
        'is_active' => (int) old('is_active', 1) === 1,
    ];
    $orderModalForm = [
        'id' => old('entity_id'),
        'visit_registration_id' => (string) old('visit_registration_id', $defaultVisitId),
        'procedure_master_id' => (string) old('procedure_master_id', $defaultMasterId),
        'quantity' => old('quantity', 1),
        'status' => old('status', 'ordered'),
        'notes' => old('notes', ''),
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Procedures" />

    <div x-data="{
        masterModalOpen: @js($masterModalOpen),
        orderModalOpen: @js($orderModalOpen),
        masterMode: @js($masterModalMode),
        orderMode: @js($orderModalMode),
        masterStoreAction: @js(route('procedure-masters.store')),
        masterUpdateBase: @js(url('/procedures/masters')),
        orderStoreAction: @js(route('procedure-orders.store')),
        orderUpdateBase: @js(url('/procedures/orders')),
        masterForm: @js($masterModalForm),
        orderForm: @js($orderModalForm),
        emptyMaster() { return { id:null, code:'', name:'', description:'', performer_scope:'both', default_fee:'', branch_prices:@js($branchOptions->mapWithKeys(fn ($branch) => [(string) $branch->id => ''])->all()), requires_doctor_order:true, is_active:true }; },
        emptyOrder() { return { id:null, visit_registration_id:@js($defaultVisitId), procedure_master_id:@js($defaultMasterId), quantity:1, status:'ordered', notes:'' }; },
        openCreateMaster() { this.masterMode = 'create'; this.masterForm = this.emptyMaster(); this.masterModalOpen = true; },
        openEditMaster(payload) { this.masterMode = 'update'; this.masterForm = payload; this.masterModalOpen = true; },
        openCreateOrder() { this.orderMode = 'create'; this.orderForm = this.emptyOrder(); this.orderModalOpen = true; },
        openEditOrder(payload) { this.orderMode = 'update'; this.orderForm = payload; this.orderModalOpen = true; },
    }" @keydown.escape.window="masterModalOpen = false; orderModalOpen = false" class="space-y-6">
        @if (session('status'))
            <x-ui.alert variant="success" :message="session('status')" />
        @endif
        @if ($errors->any())
            <x-ui.alert variant="error" :message="$errors->first()" />
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Procedures</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">Master tindakan menyimpan performer rule dan tarif per branch. Order tindakan masuk invoice saat status sudah completed.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    @if ($abilities['create'])
                        <x-ui.button type="button" x-on:click="openCreateMaster()">Tambah Master</x-ui.button>
                        <button type="button" x-on:click="openCreateOrder()" @disabled($visitOptions->isEmpty() || $masterOptions->isEmpty()) class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">Tambah Order</button>
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('procedures') }}" class="grid gap-4 md:grid-cols-[1.2fr_220px_180px_180px_180px_auto]">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari patient atau procedure" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <select name="branch" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua branch</option>@foreach ($branchOptions as $branchOption)<option value="{{ $branchOption->id }}" @selected($filters['branch'] === (string) $branchOption->id)>{{ $branchOption->code }} - {{ $branchOption->name }}</option>@endforeach</select>
                <select name="status" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua order</option><option value="ordered" @selected($filters['status'] === 'ordered')>Ordered</option><option value="in_progress" @selected($filters['status'] === 'in_progress')>In progress</option><option value="completed" @selected($filters['status'] === 'completed')>Completed</option><option value="cancelled" @selected($filters['status'] === 'cancelled')>Cancelled</option></select>
                <select name="master_status" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua master</option><option value="active" @selected($filters['master_status'] === 'active')>Active</option><option value="inactive" @selected($filters['master_status'] === 'inactive')>Inactive</option></select>
                <input type="date" name="date" value="{{ $filters['date'] }}" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <div class="flex gap-3"><x-ui.button type="submit" variant="outline">Filter</x-ui.button><a href="{{ route('procedures') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a></div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Procedure masters</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $masters->firstItem() ?? 0 }} - {{ $masters->lastItem() ?? 0 }} dari {{ $masters->total() }} master.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Procedure</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Rules</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Branch pricing</th><th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($masters as $master)
                            @php
                                $masterPayload = [
                                    'id' => $master->id,
                                    'code' => $master->code,
                                    'name' => $master->name,
                                    'description' => $master->description ?? '',
                                    'performer_scope' => $master->performer_scope,
                                    'default_fee' => (string) $master->default_fee,
                                    'branch_prices' => $branchOptions->mapWithKeys(fn ($branch) => [(string) $branch->id => (string) ($master->branchPrices->firstWhere('branch_id', $branch->id)?->price ?? '')])->all(),
                                    'requires_doctor_order' => $master->requires_doctor_order,
                                    'is_active' => $master->is_active,
                                ];
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4"><div class="font-medium text-gray-900 dark:text-white">{{ $master->name }}</div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $master->code }}</div>@if($master->description)<div class="mt-1 text-xs text-gray-400">{{ $master->description }}</div>@endif</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><div>{{ $master->performer_scope }}</div><div class="mt-1">Default Rp {{ number_format((float) $master->default_fee, 0, ',', '.') }}</div><div class="mt-1 text-xs text-gray-400">{{ $master->requires_doctor_order ? 'Perlu doctor order' : 'Bisa tanpa doctor order' }}</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">@foreach ($branchOptions as $branchOption) @php $price = $master->branchPrices->firstWhere('branch_id', $branchOption->id); @endphp <div class="flex items-center justify-between gap-3"><span>{{ $branchOption->code }}</span><span>{{ $price ? 'Rp ' . number_format((float) $price->price, 0, ',', '.') : '-' }}</span></div> @endforeach</td>
                                <td class="px-6 py-4"><div class="flex justify-end gap-2">@if ($abilities['edit'])<x-ui.icon-button title="Edit master" data-payload='@json($masterPayload)' x-on:click='openEditMaster(JSON.parse($el.dataset.payload))'><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button>@endif @if ($abilities['delete'])<form method="POST" action="{{ route('procedure-masters.delete', $master) }}" onsubmit="return confirm('Hapus master tindakan ini?')">@csrf<x-ui.icon-button type="submit" variant="danger" title="Hapus master"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></form>@endif</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada master tindakan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $masters->links() }}</div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Procedure orders</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $orders->firstItem() ?? 0 }} - {{ $orders->lastItem() ?? 0 }} dari {{ $orders->total() }} order.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Visit</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Procedure</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Execution</th><th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($orders as $order)
                            @php
                                $orderPayload = [
                                    'id' => $order->id,
                                    'visit_registration_id' => (string) $order->visit_registration_id,
                                    'procedure_master_id' => (string) $order->procedure_master_id,
                                    'quantity' => (string) $order->quantity,
                                    'status' => $order->status,
                                    'notes' => $order->notes ?? '',
                                ];
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $order->visitRegistration?->patient?->full_name }}<div class="mt-1 text-xs text-gray-400">{{ $order->visitRegistration?->patientBranchRecord?->medical_record_no }} | {{ $order->branch?->code }}</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $order->procedureMaster?->name }}<div class="mt-1 text-xs text-gray-400">Qty {{ number_format((float) $order->quantity, 2) }} | Rp {{ number_format((float) $order->subtotal, 0, ',', '.') }}</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><div>{{ $order->status }}</div><div class="mt-1 text-xs text-gray-400">Ordered by {{ $order->orderedByDoctor?->displayName() ?? '-' }}</div>@if($order->performedByUser)<div class="mt-1 text-xs text-gray-400">Performed by {{ $order->performedByUser->name }} ({{ $order->performed_by_role ?? '-' }})</div>@endif</td>
                                <td class="px-6 py-4"><div class="flex justify-end gap-2">@if ($abilities['edit'])<x-ui.icon-button title="Edit order" data-payload='@json($orderPayload)' x-on:click='openEditOrder(JSON.parse($el.dataset.payload))'><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button>@endif @if ($abilities['delete'])<form method="POST" action="{{ route('procedure-orders.delete', $order) }}" onsubmit="return confirm('Hapus order tindakan ini?')">@csrf<x-ui.icon-button type="submit" variant="danger" title="Hapus order"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></form>@endif</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada order tindakan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $orders->links() }}</div>
        </section>

        <x-ui.modal show="masterModalOpen" maxWidth="4xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800 flex items-start justify-between gap-4"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="masterMode === 'create' ? 'Tambah Master Tindakan' : 'Update Master Tindakan'"></h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tentukan performer rule dan tarif branch dari satu tempat.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="masterModalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div>
            <form method="POST" x-bind:action="masterMode === 'create' ? masterStoreAction : `${masterUpdateBase}/${masterForm.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="masterMode === 'create' ? 'procedure-master-create' : 'procedure-master-update'">
                <input type="hidden" name="entity_id" x-bind:value="masterForm.id">
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Code</label><input x-model="masterForm.code" type="text" name="code" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label><input x-model="masterForm.name" type="text" name="name" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Performer scope</label><select x-model="masterForm.performer_scope" name="performer_scope" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="doctor_only">doctor_only</option><option value="nurse_only">nurse_only</option><option value="both">both</option></select></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Default fee</label><input x-model="masterForm.default_fee" type="number" step="0.01" min="0" name="default_fee" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                </div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label><textarea x-model="masterForm.description" name="description" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                <div class="grid gap-4 md:grid-cols-2">@foreach ($branchOptions as $branchOption)<div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $branchOption->code }} price</label><input x-model="masterForm.branch_prices['{{ $branchOption->id }}']" type="number" step="0.01" min="0" name="branch_prices[{{ $branchOption->id }}]" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>@endforeach</div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div><input type="hidden" name="requires_doctor_order" x-bind:value="masterForm.requires_doctor_order ? 1 : 0"><label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300"><input x-model="masterForm.requires_doctor_order" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20"><span>Requires doctor order</span></label></div>
                    <div><input type="hidden" name="is_active" x-bind:value="masterForm.is_active ? 1 : 0"><label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300"><input x-model="masterForm.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20"><span>Active</span></label></div>
                </div>
                <div class="flex justify-end gap-3"><x-ui.button type="button" variant="outline" x-on:click="masterModalOpen = false">Batal</x-ui.button><x-ui.button type="submit" x-text="masterMode === 'create' ? 'Simpan Master' : 'Update Master'"></x-ui.button></div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="orderModalOpen" maxWidth="3xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800 flex items-start justify-between gap-4"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="orderMode === 'create' ? 'Tambah Procedure Order' : 'Update Procedure Order'"></h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Order tindakan akan memengaruhi workflow visit dan billing saat status completed.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="orderModalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div>
            <form method="POST" x-bind:action="orderMode === 'create' ? orderStoreAction : `${orderUpdateBase}/${orderForm.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="orderMode === 'create' ? 'procedure-order-create' : 'procedure-order-update'">
                <input type="hidden" name="entity_id" x-bind:value="orderForm.id">
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Visit</label><select x-model="orderForm.visit_registration_id" name="visit_registration_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">@foreach ($visitOptions as $visitOption)<option value="{{ $visitOption->id }}">{{ $visitOption->patient?->full_name }} | {{ $visitOption->patientBranchRecord?->medical_record_no }} | {{ $visitOption->branch?->code }}</option>@endforeach</select></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Procedure master</label><select x-model="orderForm.procedure_master_id" name="procedure_master_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">@foreach ($masterOptions as $masterOption)<option value="{{ $masterOption->id }}">{{ $masterOption->code }} - {{ $masterOption->name }}</option>@endforeach</select></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity</label><input x-model="orderForm.quantity" type="number" step="0.01" min="0.01" name="quantity" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label><select x-model="orderForm.status" name="status" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="ordered">ordered</option><option value="in_progress">in_progress</option><option value="completed">completed</option><option value="cancelled">cancelled</option></select></div>
                </div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label><textarea x-model="orderForm.notes" name="notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                <div class="flex justify-end gap-3"><x-ui.button type="button" variant="outline" x-on:click="orderModalOpen = false">Batal</x-ui.button><x-ui.button type="submit" x-text="orderMode === 'create' ? 'Simpan Order' : 'Update Order'"></x-ui.button></div>
            </form>
        </x-ui.modal>
    </div>
@endsection

