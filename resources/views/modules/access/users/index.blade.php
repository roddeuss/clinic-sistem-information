@php
    use Illuminate\Support\Str;

    $userModalOpen = $errors->any() && in_array(old('form_context'), ['user-create', 'user-update'], true);
    $userModalMode = old('form_context') === 'user-update' ? 'update' : 'create';
    $defaultRole = (string) ($roles->first()?->name ?? '');
    $userModalForm = [
        'id' => old('entity_id'),
        'name' => old('name', ''),
        'email' => old('email', ''),
        'role' => old('role', $defaultRole),
        'is_active' => (int) old('is_active', 1) === 1,
    ];
    $archiveModalOpen = $errors->any() && old('form_context') === 'user-archive';
    $archiveModalForm = [
        'id' => old('entity_id'),
        'name' => old('entity_name', ''),
        'reason' => old('reason', ''),
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="User Management" />

    <div
        x-data="{
            modalOpen: @js($userModalOpen),
            archiveOpen: @js($archiveModalOpen),
            mode: @js($userModalMode),
            storeAction: @js(route('users.store')),
            updateBase: @js(url('/users')),
            defaultRole: @js($defaultRole),
            form: @js($userModalForm),
            archiveForm: @js($archiveModalForm),
            openCreate() {
                this.mode = 'create';
                this.form = {
                    id: null,
                    name: '',
                    email: '',
                    role: this.defaultRole,
                    is_active: true,
                };
                this.modalOpen = true;
            },
            openEdit(user) {
                this.mode = 'update';
                this.form = {
                    id: user.id,
                    name: user.name,
                    email: user.email,
                    role: user.role,
                    is_active: user.is_active,
                };
                this.modalOpen = true;
            },
            closeModal() {
                this.modalOpen = false;
            },
            openArchive(user) {
                this.archiveForm = {
                    id: user.id,
                    name: user.name,
                    reason: '',
                };
                this.archiveOpen = true;
            },
            closeArchive() {
                this.archiveOpen = false;
            },
        }"
        @keydown.escape.window="closeModal(); closeArchive()"
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Data table user dashboard</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Kelola akun dashboard internal secara terstruktur. User tetap dipisah dari employee agar akses login dan data SDM bisa berkembang tanpa saling mengunci.
                    </p>
                </div>

                @if ($abilities['create'])
                    <x-ui.button type="button" x-on:click="openCreate()">
                        Tambah User
                    </x-ui.button>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('users') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-[1.1fr_220px_180px_220px_180px_140px_auto]">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari nama, email, atau nomor employee" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
                    <select name="role" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}" @selected($filters['role'] === $role->name)>{{ $roleLabels[$role->name]['label'] ?? Str::headline($role->name) }}</option>
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
                    <a href="{{ route('users') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">User list</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Menampilkan {{ $users->firstItem() ?? 0 }} - {{ $users->lastItem() ?? 0 }} dari {{ $users->total() }} user.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">User</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Role</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Employee</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Last Login</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($users as $user)
                            @php
                                $currentRole = (string) ($user->primaryRoleName() ?? '');
                            @endphp

                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $user->name }}</p>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $currentRole !== '' ? ($roleLabels[$currentRole]['label'] ?? Str::headline($currentRole)) : 'Belum ada role' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $user->employee?->employee_number ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $user->last_login_at?->format('d M Y H:i') ?? 'Belum login' }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $user->last_login_ip ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300' }}">
                                        {{ $user->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'])
                                            <button
                                                type="button"
                                                title="Edit user"
                                                aria-label="Edit user"
                                                data-id="{{ $user->id }}"
                                                data-name="{{ $user->name }}"
                                                data-email="{{ $user->email }}"
                                                data-role="{{ $currentRole }}"
                                                data-is-active="{{ $user->is_active ? 1 : 0 }}"
                                                x-on:click="openEdit({
                                                    id: Number($el.dataset.id),
                                                    name: $el.dataset.name,
                                                    email: $el.dataset.email,
                                                    role: $el.dataset.role,
                                                    is_active: $el.dataset.isActive === '1',
                                                })"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white"
                                            >
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                                    <path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                            </button>
                                        @endif

                                        @if ($abilities['delete'])
                                            <button
                                                type="button"
                                                title="Nonaktifkan user"
                                                aria-label="Nonaktifkan user"
                                                data-id="{{ $user->id }}"
                                                data-name="{{ $user->name }}"
                                                x-on:click="openArchive({
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
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Belum ada data user yang cocok dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                {{ $users->links() }}
            </div>
        </section>

        <x-ui.modal show="modalOpen" maxWidth="2xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="mode === 'create' ? 'Tambah User Dashboard' : 'Update User Dashboard'"></h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Simpan identitas akun dashboard, role, dan status akses login dari satu modal yang ringkas.
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
                <input type="hidden" name="form_context" x-bind:value="mode === 'create' ? 'user-create' : 'user-update'">
                <input type="hidden" name="entity_id" x-bind:value="form.id">

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama</label>
                        <input x-model="form.name" type="text" name="name" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input x-model="form.email" type="email" name="email" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <template x-if="mode === 'create'">
                        <div class="contents">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                                <input type="password" name="password" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Konfirmasi password</label>
                                <input type="password" name="password_confirmation" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            </div>
                        </div>
                    </template>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
                        <select x-model="form.role" name="role" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($roles as $role)
                                <option value="{{ $role->name }}">{{ $roleLabels[$role->name]['label'] ?? Str::headline($role->name) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end">
                        <div>
                            <input type="hidden" name="is_active" x-bind:value="form.is_active ? 1 : 0">
                            <label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input x-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                                <span>User aktif</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeModal()">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="mode === 'create' ? 'Simpan User' : 'Update User'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="archiveOpen" maxWidth="lg">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Nonaktifkan User</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            User akan diarsipkan dengan cara dinonaktifkan. Session aktif akan dicabut untuk menjaga keamanan akses.
                        </p>
                    </div>

                    <x-ui.icon-button title="Tutup modal" x-on:click="closeArchive()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </x-ui.icon-button>
                </div>
            </div>

            <form method="POST" x-bind:action="`${updateBase}/${archiveForm.id}/archive`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="user-archive">
                <input type="hidden" name="entity_id" x-bind:value="archiveForm.id">
                <input type="hidden" name="entity_name" x-bind:value="archiveForm.name">

                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                    <span class="font-medium" x-text="archiveForm.name || 'User terpilih'"></span>
                    <span> akan dinonaktifkan. Aksi ini tidak menghapus histori audit dan relasi operasional.</span>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alasan nonaktif</label>
                    <textarea x-model="archiveForm.reason" name="reason" rows="3" placeholder="Contoh: resign, offboarding, atau akun duplikat" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeArchive()">Batal</x-ui.button>
                    <x-ui.button type="submit" class="bg-rose-600 hover:bg-rose-700 focus:ring-rose-500/20">Nonaktifkan User</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection

