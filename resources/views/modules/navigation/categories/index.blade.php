@php
    $categoryModalOpen = $errors->any() && in_array(old('form_context'), ['category-create', 'category-update'], true);
    $categoryModalMode = old('form_context') === 'category-update' ? 'update' : 'create';
    $categoryModalForm = [
        'id' => old('entity_id'),
        'name' => old('name', ''),
        'sort_order' => (int) old('sort_order', 0),
        'is_active' => (int) old('is_active', 1) === 1,
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Menu Categories" />

    <div
        x-data="{
            modalOpen: @js($categoryModalOpen),
            mode: @js($categoryModalMode),
            storeAction: @js(route('menu-categories.store')),
            updateBase: @js(url('/menu-categories')),
            form: @js($categoryModalForm),
            openCreate() {
                this.mode = 'create';
                this.form = { id: null, name: '', sort_order: 0, is_active: true };
                this.modalOpen = true;
            },
            openEdit(category) {
                this.mode = 'update';
                this.form = {
                    id: category.id,
                    name: category.name,
                    sort_order: category.sort_order,
                    is_active: category.is_active,
                };
                this.modalOpen = true;
            },
            closeModal() {
                this.modalOpen = false;
            },
        }"
        @keydown.escape.window="closeModal()"
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Data table menu category</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Pisahkan pengelolaan kategori sidebar ke halaman khusus agar struktur navigasi lebih rapi dan mudah ditelusuri.
                    </p>
                </div>

                @if ($abilities['create'])
                    <x-ui.button type="button" x-on:click="openCreate()">
                        Tambah Category
                    </x-ui.button>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('menu-categories') }}" class="grid gap-4 md:grid-cols-[1.2fr_180px_auto]">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari nama atau slug category" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <select name="status" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua status</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                    </select>
                </div>

                <div class="flex items-end gap-3">
                    <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
                    <a href="{{ route('menu-categories') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Category list</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Menampilkan {{ $categories->firstItem() ?? 0 }} - {{ $categories->lastItem() ?? 0 }} dari {{ $categories->total() }} category.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Category</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Slug</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Sort</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Menus</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($categories as $category)
                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $category->name }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $category->slug }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $category->is_active ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300' }}">
                                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $category->sort_order }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $category->menus_count }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'])
                                            @php
                                                $editPayload = [
                                                    'id' => $category->id,
                                                    'name' => $category->name,
                                                    'sort_order' => $category->sort_order,
                                                    'is_active' => $category->is_active,
                                                ];
                                            @endphp

                                            <button
                                                type="button"
                                                title="Edit category"
                                                aria-label="Edit category"
                                                data-payload='@json($editPayload)' x-on:click='openEdit(JSON.parse($el.dataset.payload))'
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white"
                                            >
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                                    <path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                            </button>
                                        @endif

                                        @if ($abilities['delete'])
                                            <form method="POST" action="{{ route('menu-categories.delete', $category) }}" onsubmit="return confirm('Hapus category ini?')">
                                                @csrf
                                                <x-ui.icon-button type="submit" variant="danger" title="Hapus category">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                        <path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    </svg>
                                                </x-ui.icon-button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Belum ada data category yang cocok dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                {{ $categories->links() }}
            </div>
        </section>

        <x-ui.modal show="modalOpen" maxWidth="xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="mode === 'create' ? 'Tambah Menu Category' : 'Update Menu Category'"></h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Simpan nama category dan urutan tampilnya di sidebar.
                        </p>
                    </div>

                    <x-ui.icon-button title="Tutup modal" x-on:click="closeModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </x-ui.icon-button>
                </div>
            </div>

            <form method="POST" x-bind:action="mode === 'create' ? storeAction : `${updateBase}/${form.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="mode === 'create' ? 'category-create' : 'category-update'">
                <input type="hidden" name="entity_id" x-bind:value="form.id">

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama category</label>
                    <input x-model="form.name" type="text" name="name" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Urutan</label>
                    <input x-model="form.sort_order" type="number" name="sort_order" min="0" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>

                <div>
                    <input type="hidden" name="is_active" x-bind:value="form.is_active ? 1 : 0">
                    <label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                        <input x-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                        <span>Category aktif</span>
                    </label>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeModal()">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="mode === 'create' ? 'Simpan Category' : 'Update Category'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection

