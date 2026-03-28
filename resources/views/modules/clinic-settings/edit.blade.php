@php
    use Illuminate\Support\Str;

    $clinicModalOpen = $errors->any() && old('form_context') === 'clinic-profile';
    $branchModalOpen = $errors->any() && in_array(old('form_context'), ['branch-create', 'branch-update'], true);
    $branchModalMode = old('form_context') === 'branch-update' ? 'update' : 'create';
    $clinicModalForm = [
        'name' => old('clinic.name', $clinic?->name ?? ''),
        'code' => old('clinic.code', $clinic?->code ?? ''),
        'logo_path' => old('clinic.logo_path', $clinic?->logo_path ?? ''),
        'phone' => old('clinic.phone', $clinic?->phone ?? ''),
        'email' => old('clinic.email', $clinic?->email ?? ''),
        'address' => old('clinic.address', $clinic?->address ?? ''),
        'invoice_header' => old('clinic.invoice_header', $clinic?->invoice_header ?? ''),
    ];
    $branchModalForm = [
        'id' => old('entity_id'),
        'name' => old('branch.name', ''),
        'code' => old('branch.code', ''),
        'phone' => old('branch.phone', ''),
        'address' => old('branch.address', ''),
        'opening_time' => old('branch.opening_time', '08:00'),
        'closing_time' => old('branch.closing_time', '20:00'),
        'queue_prefix' => old('branch.queue_prefix', 'A'),
        'queue_number_padding' => (int) old('branch.queue_number_padding', 3),
        'is_active' => (int) old('branch.is_active', 1) === 1,
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Clinic & Branch Settings" />

    <div
        x-data="{
            clinicModalOpen: @js($clinicModalOpen),
            branchModalOpen: @js($branchModalOpen),
            branchMode: @js($branchModalMode),
            clinicAction: @js(route('clinic.profile')),
            branchStoreAction: @js(route('clinic-branches.store')),
            branchUpdateBase: @js(url('/clinic/branches')),
            clinicSnapshot: @js($clinicModalForm),
            clinicForm: @js($clinicModalForm),
            defaultBranch: {
                id: null,
                name: '',
                code: '',
                phone: '',
                address: '',
                opening_time: '08:00',
                closing_time: '20:00',
                queue_prefix: 'A',
                queue_number_padding: 3,
                is_active: true,
            },
            branchForm: @js($branchModalForm),
            openClinic() {
                this.clinicForm = { ...this.clinicSnapshot };
                this.clinicModalOpen = true;
            },
            closeClinic() {
                this.clinicModalOpen = false;
            },
            openBranchCreate() {
                this.branchMode = 'create';
                this.branchForm = { ...this.defaultBranch };
                this.branchModalOpen = true;
            },
            openBranchEdit(branch) {
                this.branchMode = 'update';
                this.branchForm = {
                    id: branch.id,
                    name: branch.name,
                    code: branch.code,
                    phone: branch.phone,
                    address: branch.address,
                    opening_time: branch.opening_time,
                    closing_time: branch.closing_time,
                    queue_prefix: branch.queue_prefix,
                    queue_number_padding: branch.queue_number_padding,
                    is_active: branch.is_active,
                };
                this.branchModalOpen = true;
            },
            closeBranch() {
                this.branchModalOpen = false;
            },
        }"
        @keydown.escape.window="closeClinic(); closeBranch()"
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Clinic & branch control center</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Kelola identitas klinik utama dan cabang operasional dari satu halaman. Profil klinik diatur lewat modal terpisah, sedangkan branch dikelola lewat tabel, filter, dan action icon.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    @if ($abilities['edit'])
                        <x-ui.button type="button" variant="outline" x-on:click="openClinic()">
                            Edit Profil Klinik
                        </x-ui.button>
                    @endif

                    @if ($abilities['create'])
                        <x-ui.button type="button" x-on:click="openBranchCreate()">
                            Tambah Cabang
                        </x-ui.button>
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Profil klinik utama</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Informasi ini menjadi dasar branding dashboard dan dokumen operasional.
                    </p>
                </div>

                <span class="inline-flex rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                    {{ $branches->total() }} cabang
                </span>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-medium uppercase tracking-[0.16em] text-gray-400">Nama klinik</p>
                    <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $clinic?->name ?: '-' }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-medium uppercase tracking-[0.16em] text-gray-400">Kode klinik</p>
                    <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $clinic?->code ?: '-' }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-medium uppercase tracking-[0.16em] text-gray-400">Telepon</p>
                    <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $clinic?->phone ?: '-' }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-medium uppercase tracking-[0.16em] text-gray-400">Email</p>
                    <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $clinic?->email ?: '-' }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-medium uppercase tracking-[0.16em] text-gray-400">Logo path / URL</p>
                    <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white break-all">{{ $clinic?->logo_path ?: '-' }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                    <p class="text-xs font-medium uppercase tracking-[0.16em] text-gray-400">Alamat</p>
                    <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $clinic?->address ?: '-' }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800 md:col-span-2 xl:col-span-3">
                    <p class="text-xs font-medium uppercase tracking-[0.16em] text-gray-400">Header invoice</p>
                    <p class="mt-2 whitespace-pre-line text-sm font-medium text-gray-900 dark:text-white">{{ $clinic?->invoice_header ?: '-' }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('clinic') }}" class="grid gap-4 md:grid-cols-[1.3fr_180px_auto]">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari cabang, kode, prefix, atau telepon" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
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
                    <a href="{{ route('clinic') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Branch list</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Menampilkan {{ $branches->firstItem() ?? 0 }} - {{ $branches->lastItem() ?? 0 }} dari {{ $branches->total() }} cabang.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Branch</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Contact</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Hours</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Queue</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($branches as $branch)
                            @php
                                $editPayload = [
                                    'id' => $branch->id,
                                    'name' => $branch->name,
                                    'code' => $branch->code ?? '',
                                    'phone' => $branch->phone ?? '',
                                    'address' => $branch->address ?? '',
                                    'opening_time' => filled($branch->opening_time) ? Str::of($branch->opening_time)->substr(0, 5)->value() : '08:00',
                                    'closing_time' => filled($branch->closing_time) ? Str::of($branch->closing_time)->substr(0, 5)->value() : '20:00',
                                    'queue_prefix' => $branch->queue_prefix ?? '',
                                    'queue_number_padding' => $branch->queue_number_padding,
                                    'is_active' => $branch->is_active,
                                ];
                            @endphp

                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $branch->name }}</p>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $branch->code ?: '-' }}</p>
                                        <p class="mt-2 text-xs text-gray-400">{{ $branch->address ?: '-' }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $branch->phone ?: '-' }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ filled($branch->opening_time) ? Str::of($branch->opening_time)->substr(0, 5) : '-' }}
                                    -
                                    {{ filled($branch->closing_time) ? Str::of($branch->closing_time)->substr(0, 5) : '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ ($branch->queue_prefix ?: '-') . ' / ' . $branch->queue_number_padding . ' digit' }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $branch->is_active ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300' }}">
                                        {{ $branch->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'])
                                            <button
                                                type="button"
                                                title="Edit branch"
                                                aria-label="Edit branch"
                                                data-payload='@json($editPayload)' x-on:click='openBranchEdit(JSON.parse($el.dataset.payload))'
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white"
                                            >
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                                    <path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                            </button>
                                        @endif

                                        @if ($abilities['delete'])
                                            <form method="POST" action="{{ route('clinic-branches.delete', $branch) }}" onsubmit="return confirm('Hapus cabang ini?')">
                                                @csrf
                                                <x-ui.icon-button type="submit" variant="danger" title="Hapus cabang">
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
                                    Belum ada data cabang yang cocok dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                {{ $branches->links() }}
            </div>
        </section>

        <x-ui.modal show="clinicModalOpen" maxWidth="3xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Update Profil Klinik</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Simpan identitas brand yang tampil pada dashboard dan dokumen operasional.
                        </p>
                    </div>

                    <x-ui.icon-button title="Tutup modal" x-on:click="closeClinic()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </x-ui.icon-button>
                </div>
            </div>

            <form method="POST" action="{{ route('clinic.profile') }}" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="clinic-profile">

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama klinik</label>
                        <input x-model="clinicForm.name" type="text" name="clinic[name]" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Kode klinik</label>
                        <input x-model="clinicForm.code" type="text" name="clinic[code]" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Logo path / URL</label>
                        <input x-model="clinicForm.logo_path" type="text" name="clinic[logo_path]" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Telepon</label>
                        <input x-model="clinicForm.phone" type="text" name="clinic[phone]" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input x-model="clinicForm.email" type="email" name="clinic[email]" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alamat</label>
                        <input x-model="clinicForm.address" type="text" name="clinic[address]" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Header invoice</label>
                        <textarea x-model="clinicForm.invoice_header" name="clinic[invoice_header]" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeClinic()">Batal</x-ui.button>
                    <x-ui.button type="submit">Simpan Profil Klinik</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="branchModalOpen" maxWidth="3xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="branchMode === 'create' ? 'Tambah Cabang' : 'Update Cabang'"></h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Atur identitas cabang, jam buka, serta format nomor antrian per lokasi.
                        </p>
                    </div>

                    <x-ui.icon-button title="Tutup modal" x-on:click="closeBranch()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </x-ui.icon-button>
                </div>
            </div>

            <form method="POST" x-bind:action="branchMode === 'create' ? branchStoreAction : `${branchUpdateBase}/${branchForm.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="branchMode === 'create' ? 'branch-create' : 'branch-update'">
                <input type="hidden" name="entity_id" x-bind:value="branchForm.id">

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama cabang</label>
                        <input x-model="branchForm.name" type="text" name="branch[name]" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Kode cabang</label>
                        <input x-model="branchForm.code" type="text" name="branch[code]" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Telepon</label>
                        <input x-model="branchForm.phone" type="text" name="branch[phone]" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alamat</label>
                        <input x-model="branchForm.address" type="text" name="branch[address]" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Jam buka</label>
                        <input x-model="branchForm.opening_time" type="time" name="branch[opening_time]" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Jam tutup</label>
                        <input x-model="branchForm.closing_time" type="time" name="branch[closing_time]" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Prefix antrian</label>
                        <input x-model="branchForm.queue_prefix" type="text" name="branch[queue_prefix]" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Digit nomor antrian</label>
                        <input x-model="branchForm.queue_number_padding" type="number" name="branch[queue_number_padding]" min="2" max="6" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div class="flex items-end">
                        <div>
                            <input type="hidden" name="branch[is_active]" x-bind:value="branchForm.is_active ? 1 : 0">
                            <label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input x-model="branchForm.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                                <span>Cabang aktif</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeBranch()">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="branchMode === 'create' ? 'Simpan Cabang' : 'Update Cabang'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection

