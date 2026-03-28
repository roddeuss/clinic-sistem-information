@php
    $modalOpen = $errors->any() && in_array(old('form_context'), ['supplier-create', 'supplier-update'], true);
    $modalMode = old('form_context') === 'supplier-update' ? 'update' : 'create';
    $modalForm = [
        'id' => old('entity_id'),
        'code' => old('code', ''),
        'name' => old('name', ''),
        'contact_person' => old('contact_person', ''),
        'phone' => old('phone', ''),
        'email' => old('email', ''),
        'npwp' => old('npwp', ''),
        'payment_term_days' => old('payment_term_days', ''),
        'address' => old('address', ''),
        'notes' => old('notes', ''),
        'is_active' => (int) old('is_active', 1) === 1,
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Suppliers" />

    <div x-data="{
        modalOpen: @js($modalOpen),
        modalMode: @js($modalMode),
        storeAction: @js(route('suppliers.store')),
        updateBase: @js(url('/suppliers')),
        form: @js($modalForm),
        emptyForm() { return { id:null, code:'', name:'', contact_person:'', phone:'', email:'', npwp:'', payment_term_days:'', address:'', notes:'', is_active:true }; },
        openCreate() { this.modalMode = 'create'; this.form = this.emptyForm(); this.modalOpen = true; },
        openEdit(payload) { this.modalMode = 'update'; this.form = payload; this.modalOpen = true; },
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Suppliers</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">Master supplier lintas branch untuk pengadaan farmasi, batch receipt, termin pembayaran, dan dokumen perpajakan procurement.</p>
                </div>
                @if ($abilities['create'])
                    <x-ui.button type="button" x-on:click="openCreate()">Tambah Supplier</x-ui.button>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('suppliers') }}" class="grid gap-4 md:grid-cols-[1fr_180px_auto]">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari code, nama, PIC, atau kontak" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <select name="status" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua status</option><option value="active" @selected($filters['status'] === 'active')>Active</option><option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option></select>
                <div class="flex gap-3"><x-ui.button type="submit" variant="outline">Filter</x-ui.button><a href="{{ route('suppliers') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a></div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Supplier list</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $suppliers->firstItem() ?? 0 }} - {{ $suppliers->lastItem() ?? 0 }} dari {{ $suppliers->total() }} supplier.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Supplier</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Contact</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Tax & Term</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Usage</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th><th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($suppliers as $supplier)
                            @php
                                $payload = [
                                    'id' => $supplier->id,
                                    'code' => $supplier->code,
                                    'name' => $supplier->name,
                                    'contact_person' => $supplier->contact_person ?? '',
                                    'phone' => $supplier->phone ?? '',
                                    'email' => $supplier->email ?? '',
                                    'npwp' => $supplier->npwp ?? '',
                                    'payment_term_days' => $supplier->payment_term_days ?? '',
                                    'address' => $supplier->address ?? '',
                                    'notes' => $supplier->notes ?? '',
                                    'is_active' => $supplier->is_active,
                                ];
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4"><div class="font-medium text-gray-900 dark:text-white">{{ $supplier->name }}</div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $supplier->code }}</div>@if($supplier->notes)<div class="mt-1 text-xs text-gray-400">{{ $supplier->notes }}</div>@endif</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><div>{{ $supplier->contact_person ?: '-' }}</div><div class="mt-1 text-xs text-gray-400">{{ $supplier->phone ?: '-' }}</div><div class="mt-1 text-xs text-gray-400">{{ $supplier->email ?: '-' }}</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"><div>NPWP: {{ $supplier->npwp ?: '-' }}</div><div class="mt-1 text-xs text-gray-400">Termin: {{ $supplier->payment_term_days !== null ? $supplier->payment_term_days . ' hari' : '-' }}</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $supplier->medicine_batches_count }} batch<div class="mt-1 text-xs text-gray-400">{{ $supplier->address ?: '-' }}</div></td>
                                <td class="px-6 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $supplier->is_active ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300' }}">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="px-6 py-4"><div class="flex justify-end gap-2">@if ($abilities['edit'])<x-ui.icon-button title="Edit supplier" data-payload='@json($payload)' x-on:click='openEdit(JSON.parse($el.dataset.payload))'><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button>@endif @if ($abilities['delete'])<form method="POST" action="{{ route('suppliers.delete', $supplier) }}" onsubmit="return confirm('Hapus supplier ini?')">@csrf<x-ui.icon-button type="submit" variant="danger" title="Hapus supplier"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></form>@endif</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada supplier.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $suppliers->links() }}</div>
        </section>

        <x-ui.modal show="modalOpen" maxWidth="3xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="modalMode === 'create' ? 'Tambah Supplier' : 'Update Supplier'"></h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Supplier berlaku lintas branch dan akan dipakai di batch receipt serta procurement.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="modalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div>
            <form method="POST" x-bind:action="modalMode === 'create' ? storeAction : `${updateBase}/${form.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="modalMode === 'create' ? 'supplier-create' : 'supplier-update'">
                <input type="hidden" name="entity_id" x-bind:value="form.id">
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Code</label><input x-model="form.code" type="text" name="code" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label><input x-model="form.name" type="text" name="name" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Contact person</label><input x-model="form.contact_person" type="text" name="contact_person" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label><input x-model="form.phone" type="text" name="phone" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label><input x-model="form.email" type="email" name="email" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">NPWP</label><input x-model="form.npwp" type="text" name="npwp" placeholder="00.000.000.0-000.000" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Payment term (days)</label><input x-model="form.payment_term_days" type="number" min="0" max="365" name="payment_term_days" placeholder="30" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div class="flex items-end"><div><input type="hidden" name="is_active" x-bind:value="form.is_active ? 1 : 0"><label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300"><input x-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20"><span>Active</span></label></div></div>
                </div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Address</label><textarea x-model="form.address" name="address" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label><textarea x-model="form.notes" name="notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                <div class="flex justify-end gap-3"><x-ui.button type="button" variant="outline" x-on:click="modalOpen = false">Batal</x-ui.button><x-ui.button type="submit" x-text="modalMode === 'create' ? 'Simpan Supplier' : 'Update Supplier'"></x-ui.button></div>
            </form>
        </x-ui.modal>
    </div>
@endsection

