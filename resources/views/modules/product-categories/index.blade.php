@php
    $modalOpen = $errors->any() && in_array(old('form_context'), ['product-category-create', 'product-category-update'], true);
    $modalMode = old('form_context') === 'product-category-update' ? 'update' : 'create';
    $modalForm = [
        'id' => old('entity_id'),
        'code' => old('code', ''),
        'name' => old('name', ''),
        'description' => old('description', ''),
        'sort_order' => old('sort_order', 0),
        'is_active' => (int) old('is_active', 1) === 1,
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Product Categories" />

    <div x-data="{
        modalOpen: @js($modalOpen),
        modalMode: @js($modalMode),
        storeAction: @js(route('product-categories.store')),
        updateBase: @js(url('/product-categories')),
        form: @js($modalForm),
        emptyForm() { return { id:null, code:'', name:'', description:'', sort_order:0, is_active:true }; },
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Product Categories</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">Kategori produk flat untuk merapikan master medicine dan inventory lintas branch.</p>
                </div>
                @if ($abilities['create'])
                    <x-ui.button type="button" x-on:click="openCreate()">Tambah Category</x-ui.button>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('product-categories') }}" class="grid gap-4 md:grid-cols-[1fr_180px_auto]">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari code atau nama category" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <select name="status" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"><option value="">Semua status</option><option value="active" @selected($filters['status'] === 'active')>Active</option><option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option></select>
                <div class="flex gap-3"><x-ui.button type="submit" variant="outline">Filter</x-ui.button><a href="{{ route('product-categories') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a></div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Category list</h2><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $categories->firstItem() ?? 0 }} - {{ $categories->lastItem() ?? 0 }} dari {{ $categories->total() }} category.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]"><tr><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Category</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Usage</th><th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th><th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($categories as $category)
                            @php
                                $payload = [
                                    'id' => $category->id,
                                    'code' => $category->code,
                                    'name' => $category->name,
                                    'description' => $category->description ?? '',
                                    'sort_order' => $category->sort_order,
                                    'is_active' => $category->is_active,
                                ];
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4"><div class="font-medium text-gray-900 dark:text-white">{{ $category->name }}</div><div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $category->code }}</div>@if($category->description)<div class="mt-1 text-xs text-gray-400">{{ $category->description }}</div>@endif<div class="mt-1 text-xs text-gray-400">Sort {{ $category->sort_order }}</div></td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $category->medicines_count }} medicine</td>
                                <td class="px-6 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $category->is_active ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="px-6 py-4"><div class="flex justify-end gap-2">@if ($abilities['edit'])<x-ui.icon-button title="Edit category" data-payload='@json($payload)' x-on:click='openEdit(JSON.parse($el.dataset.payload))'><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button>@endif @if ($abilities['delete'])<form method="POST" action="{{ route('product-categories.delete', $category) }}" onsubmit="return confirm('Hapus product category ini?')">@csrf<x-ui.icon-button type="submit" variant="danger" title="Hapus category"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></form>@endif</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada product category.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $categories->links() }}</div>
        </section>

        <x-ui.modal show="modalOpen" maxWidth="3xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800"><div><h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="modalMode === 'create' ? 'Tambah Product Category' : 'Update Product Category'"></h3><p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Category ini akan dipakai di master medicine dan inventory.</p></div><x-ui.icon-button title="Tutup modal" x-on:click="modalOpen = false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></x-ui.icon-button></div>
            <form method="POST" x-bind:action="modalMode === 'create' ? storeAction : `${updateBase}/${form.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="modalMode === 'create' ? 'product-category-create' : 'product-category-update'">
                <input type="hidden" name="entity_id" x-bind:value="form.id">
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Code</label><input x-model="form.code" type="text" name="code" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label><input x-model="form.name" type="text" name="name" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Sort order</label><input x-model="form.sort_order" type="number" min="0" name="sort_order" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></div>
                    <div class="flex items-end"><div><input type="hidden" name="is_active" x-bind:value="form.is_active ? 1 : 0"><label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300"><input x-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20"><span>Active</span></label></div></div>
                </div>
                <div><label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label><textarea x-model="form.description" name="description" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea></div>
                <div class="flex justify-end gap-3"><x-ui.button type="button" variant="outline" x-on:click="modalOpen = false">Batal</x-ui.button><x-ui.button type="submit" x-text="modalMode === 'create' ? 'Simpan Category' : 'Update Category'"></x-ui.button></div>
            </form>
        </x-ui.modal>
    </div>
@endsection

