@php
    $defaultBranchId = (string) ($branchOptions->first()?->id ?? '');
    $sectionModalOpen = $errors->any() && in_array(old('form_context'), ['section-create', 'section-update'], true);
    $sectionModalMode = old('form_context') === 'section-update' ? 'update' : 'create';
    $sectionModalForm = [
        'id' => old('entity_id'),
        'branch_id' => (string) old('branch_id', $defaultBranchId),
        'name' => old('name', ''),
        'type' => old('type', 'regular'),
        'queue_prefix' => old('queue_prefix', ''),
        'queue_number_padding' => (int) old('queue_number_padding', 3),
        'allow_appointment' => (int) old('allow_appointment', 1) === 1,
        'allow_walk_in' => (int) old('allow_walk_in', 1) === 1,
        'description' => old('description', ''),
        'sort_order' => (int) old('sort_order', 0),
        'is_active' => (int) old('is_active', 1) === 1,
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Sections" />

    <div
        x-data="{
            modalOpen: @js($sectionModalOpen),
            mode: @js($sectionModalMode),
            storeAction: @js(route('sections.store')),
            updateBase: @js(url('/sections')),
            defaultBranchId: @js($defaultBranchId),
            hasBranches: @js($branchOptions->isNotEmpty()),
            form: @js($sectionModalForm),
            openCreate() {
                this.mode = 'create';
                this.form = {
                    id: null,
                    branch_id: this.defaultBranchId,
                    name: '',
                    type: 'regular',
                    queue_prefix: '',
                    queue_number_padding: 3,
                    allow_appointment: true,
                    allow_walk_in: true,
                    description: '',
                    sort_order: 0,
                    is_active: true,
                };
                this.modalOpen = true;
            },
            openEdit(section) {
                this.mode = 'update';
                this.form = {
                    id: section.id,
                    branch_id: section.branch_id,
                    name: section.name,
                    type: section.type,
                    queue_prefix: section.queue_prefix,
                    queue_number_padding: section.queue_number_padding,
                    allow_appointment: section.allow_appointment,
                    allow_walk_in: section.allow_walk_in,
                    description: section.description,
                    sort_order: section.sort_order,
                    is_active: section.is_active,
                };
                this.modalOpen = true;
            },
            applyTypeDefaults() {
                if (this.form.type === 'emergency' && this.mode === 'create') {
                    this.form.allow_appointment = false;
                    this.form.allow_walk_in = true;
                }
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Data table sections</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Kelola tujuan layanan per branch untuk antrian dan booking appointment. Section emergency dipisah lewat type khusus agar operasional tetap konsisten di multi-clinic.
                    </p>
                    @if ($branchOptions->isEmpty())
                        <p class="mt-3 text-sm font-medium text-amber-600 dark:text-amber-300">
                            Tambahkan branch dulu di halaman clinic agar section bisa dibuat.
                        </p>
                    @endif
                </div>

                @if ($abilities['create'] && $branchOptions->isNotEmpty())
                    <x-ui.button type="button" x-on:click="openCreate()">
                        Tambah Section
                    </x-ui.button>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('sections') }}" class="grid gap-4 md:grid-cols-[1.2fr_220px_180px_180px_auto]">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari section, code, prefix, branch, atau deskripsi" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Branch</label>
                    <select name="branch" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua branch</option>
                        @foreach ($branchOptions as $branchOption)
                            <option value="{{ $branchOption->id }}" @selected($filters['branch'] === (string) $branchOption->id)>{{ $branchOption->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                    <select name="type" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua type</option>
                        <option value="regular" @selected($filters['type'] === 'regular')>Regular</option>
                        <option value="emergency" @selected($filters['type'] === 'emergency')>Emergency</option>
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
                    <a href="{{ route('sections') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Section list</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Menampilkan {{ $sections->firstItem() ?? 0 }} - {{ $sections->lastItem() ?? 0 }} dari {{ $sections->total() }} section.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Section</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Branch</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Type</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Queue</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Access</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Sort</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($sections as $section)
                            @php
                                $editPayload = [
                                    'id' => $section->id,
                                    'branch_id' => (string) $section->branch_id,
                                    'name' => $section->name,
                                    'type' => $section->type,
                                    'queue_prefix' => $section->queue_prefix,
                                    'queue_number_padding' => $section->queue_number_padding,
                                    'allow_appointment' => $section->allow_appointment,
                                    'allow_walk_in' => $section->allow_walk_in,
                                    'description' => $section->description ?? '',
                                    'sort_order' => $section->sort_order,
                                    'is_active' => $section->is_active,
                                ];
                            @endphp

                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $section->name }}</p>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $section->code }}</p>
                                        @if ($section->description)
                                            <p class="mt-2 text-xs text-gray-400">{{ $section->description }}</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $section->branch?->name ?? '-' }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $section->branch?->code ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $section->type === 'emergency' ? 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-300' : 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300' }}">
                                        {{ $section->type === 'emergency' ? 'Emergency' : 'Regular' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $section->queue_prefix }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $section->queue_number_padding }} digit</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $section->allow_appointment ? 'Appointment: Yes' : 'Appointment: No' }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $section->allow_walk_in ? 'Walk-in: Yes' : 'Walk-in: No' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $section->is_active ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300' }}">
                                        {{ $section->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $section->sort_order }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'])
                                            <button
                                                type="button"
                                                title="Edit section"
                                                aria-label="Edit section"
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
                                            <form method="POST" action="{{ route('sections.delete', $section) }}" onsubmit="return confirm('Hapus section ini?')">
                                                @csrf
                                                <x-ui.icon-button type="submit" variant="danger" title="Hapus section">
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
                                    Belum ada data section yang cocok dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                {{ $sections->links() }}
            </div>
        </section>

        <x-ui.modal show="modalOpen" maxWidth="3xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="mode === 'create' ? 'Tambah Section' : 'Update Section'"></h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Code akan dibuat otomatis dari nama section. Gunakan type `Emergency` untuk jalur layanan darurat yang dipisah dari section reguler.
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
                <input type="hidden" name="form_context" x-bind:value="mode === 'create' ? 'section-create' : 'section-update'">
                <input type="hidden" name="entity_id" x-bind:value="form.id">

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Branch</label>
                        <select x-model="form.branch_id" name="branch_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($branchOptions as $branchOption)
                                <option value="{{ $branchOption->id }}">{{ $branchOption->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                        <select x-model="form.type" x-on:change="applyTypeDefaults()" name="type" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="regular">Regular</option>
                            <option value="emergency">Emergency</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Section name</label>
                        <input x-model="form.name" type="text" name="name" placeholder="mis. General" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Queue prefix</label>
                        <input x-model="form.queue_prefix" type="text" name="queue_prefix" placeholder="GEN" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm uppercase text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Boleh dikosongkan. Sistem akan membuat prefix default dari nama section.</p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Digit nomor antrian</label>
                        <input x-model="form.queue_number_padding" type="number" name="queue_number_padding" min="2" max="6" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Urutan</label>
                        <input x-model="form.sort_order" type="number" name="sort_order" min="0" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                        <textarea x-model="form.description" name="description" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                    </div>

                    <div class="flex items-end">
                        <div>
                            <input type="hidden" name="allow_appointment" x-bind:value="form.allow_appointment ? 1 : 0">
                            <label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input x-model="form.allow_appointment" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                                <span>Izinkan appointment</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-end">
                        <div>
                            <input type="hidden" name="allow_walk_in" x-bind:value="form.allow_walk_in ? 1 : 0">
                            <label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input x-model="form.allow_walk_in" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                                <span>Izinkan walk-in</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-end md:col-span-2">
                        <div>
                            <input type="hidden" name="is_active" x-bind:value="form.is_active ? 1 : 0">
                            <label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input x-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                                <span>Section aktif</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeModal()">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="mode === 'create' ? 'Simpan Section' : 'Update Section'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection

