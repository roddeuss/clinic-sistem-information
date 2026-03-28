@php
    use Illuminate\Support\Str;

    $systemRoles = array_keys(config('csi_access.roles'));
    $allPermissionNames = collect($modules)
        ->flatMap(fn (array $module) => collect($module['permissions'])->pluck('name'))
        ->values()
        ->all();
    $roleModalOpen = $errors->any() && in_array(old('form_context'), ['role-create', 'role-update'], true);
    $roleModalMode = old('form_context') === 'role-update' ? 'update' : 'create';
    $roleModalForm = [
        'id' => old('entity_id'),
        'name' => old('name', ''),
        'permissions' => array_values((array) old('permissions', [])),
    ];
    $roleDeleteModalOpen = $errors->any() && old('form_context') === 'role-delete';
    $roleDeleteModalForm = [
        'id' => old('entity_id'),
        'name' => old('entity_name', ''),
        'reason' => old('reason', ''),
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Role & Permission" />

    <div
        x-data="{
            modalOpen: @js($roleModalOpen),
            deleteModalOpen: @js($roleDeleteModalOpen),
            mode: @js($roleModalMode),
            storeAction: @js(route('roles.store')),
            updateBase: @js(url('/roles')),
            allPermissions: @js($allPermissionNames),
            form: @js($roleModalForm),
            deleteForm: @js($roleDeleteModalForm),
            openCreate() {
                this.mode = 'create';
                this.form = {
                    id: null,
                    name: '',
                    permissions: [],
                };
                this.modalOpen = true;
            },
            openEdit(role) {
                this.mode = 'update';
                this.form = {
                    id: role.id,
                    name: role.name,
                    permissions: role.permissions ?? [],
                };
                this.modalOpen = true;
            },
            selectAllPermissions() {
                this.form.permissions = [...this.allPermissions];
            },
            clearPermissions() {
                this.form.permissions = [];
            },
            closeModal() {
                this.modalOpen = false;
            },
            openDelete(role) {
                this.deleteForm = {
                    id: role.id,
                    name: role.name,
                    reason: '',
                };
                this.deleteModalOpen = true;
            },
            closeDelete() {
                this.deleteModalOpen = false;
            },
        }"
        @keydown.escape.window="closeModal(); closeDelete()"
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Data table role & permission</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Kelola role dashboard dari satu list yang ringan. Hak akses `view`, `create`, `edit`, dan `delete` per modul diatur per role agar sidebar dan endpoint mengikuti role aktif.
                    </p>
                </div>

                @if ($abilities['create'])
                    <x-ui.button type="button" x-on:click="openCreate()">
                        Tambah Role
                    </x-ui.button>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('roles') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-[1.1fr_220px_220px_180px_140px_auto]">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari role key" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Scope</label>
                    <select name="scope" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua scope</option>
                        <option value="system" @selected($filters['scope'] === 'system')>System role</option>
                        <option value="custom" @selected($filters['scope'] === 'custom')>Custom role</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Sort by</label>
                    <select name="sort_by" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        @foreach ($sortOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['sort_by'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Direction</label>
                    <select name="sort_direction" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="asc" @selected($filters['sort_direction'] === 'asc')>Ascending</option>
                        <option value="desc" @selected($filters['sort_direction'] === 'desc')>Descending</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Per page</label>
                    <select name="per_page" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected($filters['per_page'] === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-3">
                    <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
                    <a href="{{ route('roles') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Role list</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Menampilkan {{ $roles->firstItem() ?? 0 }} - {{ $roles->lastItem() ?? 0 }} dari {{ $roles->total() }} role.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Role</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Scope</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Permissions</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Users</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($roles as $role)
                            @php
                                $isSystemRole = in_array($role->name, $systemRoles, true);
                            @endphp

                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $roleLabels[$role->name]['label'] ?? Str::headline($role->name) }}</p>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $role->name }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $isSystemRole ? 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300' }}">
                                        {{ $isSystemRole ? 'System' : 'Custom' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $role->permissions_count }} permission
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $role->users_count }} user
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'])
                                            <button
                                                type="button"
                                                title="Edit role"
                                                aria-label="Edit role"
                                                data-id="{{ $role->id }}"
                                                data-name="{{ $role->name }}"
                                                data-permissions='@json(array_values($role->permissions->pluck('name')->all()), JSON_HEX_APOS | JSON_HEX_QUOT)'
                                                x-on:click="openEdit({
                                                    id: Number($el.dataset.id),
                                                    name: $el.dataset.name,
                                                    permissions: JSON.parse($el.dataset.permissions || '[]'),
                                                })"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white"
                                            >
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                                    <path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                            </button>
                                        @endif

                                        @if ($abilities['delete'] && ! $isSystemRole)
                                            <button
                                                type="button"
                                                title="Hapus role"
                                                aria-label="Hapus role"
                                                data-id="{{ $role->id }}"
                                                data-name="{{ $role->name }}"
                                                x-on:click="openDelete({
                                                    id: Number($el.dataset.id),
                                                    name: $el.dataset.name,
                                                })"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-200 bg-white text-rose-600 transition hover:bg-rose-50 hover:text-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500/20 dark:border-rose-500/30 dark:bg-gray-900 dark:text-rose-300 dark:hover:bg-rose-500/10"
                                            >
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M10 11V17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    <path d="M14 11V17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    <path d="M4 7H20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    <path d="M6 7L7 19C7.06091 19.6582 7.61324 20.1602 8.2742 20.1602H15.7258C16.3868 20.1602 16.9391 19.6582 17 19L18 7" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                                    <path d="M9 7V5.6C9 5.26863 9.26863 5 9.6 5H14.4C14.7314 5 15 5.26863 15 5.6V7" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Belum ada data role yang cocok dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                {{ $roles->links() }}
            </div>
        </section>

        <x-ui.modal show="modalOpen" maxWidth="4xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="mode === 'create' ? 'Tambah Role Baru' : 'Update Hak Akses Role'"></h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Role baru disimpan sebagai slug key. Saat update, checklist permission akan mengikuti modul backend yang sudah dikonfigurasi.
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
                <input type="hidden" name="form_context" x-bind:value="mode === 'create' ? 'role-create' : 'role-update'">
                <input type="hidden" name="entity_id" x-bind:value="form.id">

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Role key</label>
                    <input x-model="form.name" type="text" name="name" placeholder="mis. Inventory Admin" x-bind:readonly="mode === 'update'" x-bind:class="mode === 'update' ? 'bg-gray-50 dark:bg-gray-800' : ''" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>

                <template x-if="mode === 'update'">
                    <div class="space-y-4">
                        <div class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-800 dark:bg-white/[0.02] sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                <span class="font-semibold text-gray-900 dark:text-white" x-text="form.permissions.length"></span>
                                permission dipilih.
                            </p>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    x-on:click="selectAllPermissions()"
                                    class="inline-flex h-10 items-center rounded-xl border border-brand-200 bg-brand-50 px-4 text-sm font-medium text-brand-700 transition hover:bg-brand-100 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-300"
                                >
                                    Check all
                                </button>
                                <button
                                    type="button"
                                    x-on:click="clearPermissions()"
                                    class="inline-flex h-10 items-center rounded-xl border border-gray-200 bg-white px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                                >
                                    Clear all
                                </button>
                            </div>
                        </div>

                        @foreach ($modules as $module)
                            <section class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $module['label'] }}</h4>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    @foreach ($module['permissions'] as $permission)
                                        <label class="flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-300">
                                            <input
                                                x-model="form.permissions"
                                                type="checkbox"
                                                name="permissions[]"
                                                value="{{ $permission['name'] }}"
                                                class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20"
                                            >
                                            <span>{{ Str::headline($permission['action']) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>
                </template>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeModal()">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="mode === 'create' ? 'Simpan Role' : 'Update Role'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="deleteModalOpen" maxWidth="lg">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Hapus Custom Role</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Hanya custom role yang tidak dipakai user mana pun yang bisa dihapus.
                        </p>
                    </div>

                    <x-ui.icon-button title="Tutup modal" x-on:click="closeDelete()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </x-ui.icon-button>
                </div>
            </div>

            <form method="POST" x-bind:action="`${updateBase}/${deleteForm.id}/delete`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="role-delete">
                <input type="hidden" name="entity_id" x-bind:value="deleteForm.id">
                <input type="hidden" name="entity_name" x-bind:value="deleteForm.name">

                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                    Role <span class="font-medium" x-text="deleteForm.name || 'terpilih'"></span> akan dihapus permanen jika tidak dipakai user.
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alasan hapus</label>
                    <textarea x-model="deleteForm.reason" name="reason" rows="3" placeholder="Contoh: role tidak dipakai lagi atau duplikat" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeDelete()">Batal</x-ui.button>
                    <x-ui.button type="submit" class="bg-rose-600 hover:bg-rose-700 focus:ring-rose-500/20">Hapus Role</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection

