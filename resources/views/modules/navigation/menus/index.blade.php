@php
    use App\Helpers\MenuHelper;

    $menuModalOpen = $errors->any() && in_array(old('form_context'), ['menu-create', 'menu-update'], true);
    $menuModalMode = old('form_context') === 'menu-update' ? 'update' : 'create';
    $menuModalForm = [
        'id' => old('entity_id'),
        'menu_category_id' => (string) old('menu_category_id', $categoryOptions->first()?->id ?? ''),
        'parent_id' => old('parent_id', ''),
        'title' => old('title', ''),
        'route_name' => old('route_name', ''),
        'icon' => old('icon', 'box'),
        'sort_order' => (int) old('sort_order', 0),
        'is_active' => (int) old('is_active', 1) === 1,
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Menu Items" />

    <div
        x-data="{
            modalOpen: @js($menuModalOpen),
            mode: @js($menuModalMode),
            storeAction: @js(route('menu-items.store')),
            updateBase: @js(url('/menus')),
            form: @js($menuModalForm),
            openCreate() {
                this.mode = 'create';
                this.form = {
                    id: null,
                    menu_category_id: @js((string) ($categoryOptions->first()?->id ?? '')),
                    parent_id: '',
                    title: '',
                    route_name: '',
                    icon: 'box',
                    sort_order: 0,
                    is_active: true,
                };
                this.modalOpen = true;
            },
            openEdit(menu) {
                this.mode = 'update';
                this.form = {
                    id: menu.id,
                    menu_category_id: menu.menu_category_id,
                    parent_id: menu.parent_id,
                    title: menu.title,
                    route_name: menu.route_name,
                    icon: menu.icon,
                    sort_order: menu.sort_order,
                    is_active: menu.is_active,
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Data table menu items</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Kelola item sidebar, path tujuan, icon, dan parent menu dari halaman terpisah yang lebih fokus.
                    </p>
                </div>

                @if ($abilities['create'])
                    <x-ui.button type="button" x-on:click="openCreate()">
                        Tambah Menu
                    </x-ui.button>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('menus') }}" class="grid gap-4 md:grid-cols-[1.3fr_220px_180px_auto]">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari title, path, slug, atau icon" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                    <select name="category" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua category</option>
                        @foreach ($categoryOptions as $categoryOption)
                            <option value="{{ $categoryOption->id }}" @selected($filters['category'] === (string) $categoryOption->id)>{{ $categoryOption->name }}</option>
                        @endforeach
                    </select>
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
                    <a href="{{ route('menus') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Menu list</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Menampilkan {{ $menus->firstItem() ?? 0 }} - {{ $menus->lastItem() ?? 0 }} dari {{ $menus->total() }} menu.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Menu</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Category</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Parent</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Path</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Icon</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Sort</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($menus as $menu)
                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <div class="flex items-start gap-3">
                                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 text-gray-700 dark:bg-white/[0.04] dark:text-gray-200">
                                            {!! MenuHelper::getIconSvg($menu->icon) !!}
                                        </span>
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">{{ $menu->title }}</p>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $menu->slug }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $menu->category?->name ?? '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $menu->parent?->title ?? '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $menu->route_name ?? '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $menu->icon }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $menu->is_active ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300' }}">
                                        {{ $menu->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $menu->sort_order }}
                                    @if ($menu->children_count > 0)
                                        <div class="mt-1 text-xs text-gray-400">{{ $menu->children_count }} submenu</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'])
                                            @php
                                                $editPayload = [
                                                    'id' => $menu->id,
                                                    'menu_category_id' => (string) $menu->menu_category_id,
                                                    'parent_id' => $menu->parent_id ? (string) $menu->parent_id : '',
                                                    'title' => $menu->title,
                                                    'route_name' => $menu->route_name ?? '',
                                                    'icon' => $menu->icon,
                                                    'sort_order' => $menu->sort_order,
                                                    'is_active' => $menu->is_active,
                                                ];
                                            @endphp

                                            <button
                                                type="button"
                                                title="Edit menu"
                                                aria-label="Edit menu"
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
                                            <form method="POST" action="{{ route('menu-items.delete', $menu) }}" onsubmit="return confirm('Hapus menu ini?')">
                                                @csrf
                                                <x-ui.icon-button type="submit" variant="danger" title="Hapus menu">
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
                                <td colspan="8" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Belum ada data menu yang cocok dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                {{ $menus->links() }}
            </div>
        </section>

        <x-ui.modal show="modalOpen" maxWidth="3xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="mode === 'create' ? 'Tambah Menu Item' : 'Update Menu Item'"></h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Gunakan path internal seperti `/menus` atau `/users` agar item sidebar mudah dibaca dan dirawat.
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
                <input type="hidden" name="form_context" x-bind:value="mode === 'create' ? 'menu-create' : 'menu-update'">
                <input type="hidden" name="entity_id" x-bind:value="form.id">

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
                        <select x-model="form.menu_category_id" name="menu_category_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($categoryOptions as $categoryOption)
                                <option value="{{ $categoryOption->id }}">{{ $categoryOption->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Parent menu</label>
                        <select x-model="form.parent_id" name="parent_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Tidak ada</option>
                            @foreach ($parentMenus as $parentMenu)
                                <option value="{{ $parentMenu->id }}">{{ $parentMenu->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Judul menu</label>
                        <input x-model="form.title" type="text" name="title" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Menu path</label>
                        <input x-model="form.route_name" type="text" name="route_name" placeholder="/menus" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Contoh: `/dashboard`, `/users`, `/menus`.</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Icon key</label>
                        <input x-model="form.icon" type="text" name="icon" placeholder="box" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Contoh: `box`, `dashboard`, `forms`, `tables`.</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Urutan</label>
                        <input x-model="form.sort_order" type="number" name="sort_order" min="0" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div class="flex items-end">
                        <div>
                            <input type="hidden" name="is_active" x-bind:value="form.is_active ? 1 : 0">
                            <label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input x-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                                <span>Menu aktif</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeModal()">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="mode === 'create' ? 'Simpan Menu' : 'Update Menu'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection

