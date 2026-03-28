@php
    $modalOpen = $errors->any() && in_array(old('form_context'), ['icd10-create', 'icd10-update'], true);
    $modalMode = old('form_context') === 'icd10-update' ? 'update' : 'create';
    $modalForm = [
        'id' => old('entity_id'),
        'chapter_code' => old('chapter_code', ''),
        'code' => old('code', ''),
        'name_en' => old('name_en', ''),
        'name_id' => old('name_id', ''),
        'description' => old('description', ''),
        'is_active' => (int) old('is_active', 1) === 1,
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="ICD-10 Master" />

    <div
        x-data="{
            modalOpen: @js($modalOpen),
            mode: @js($modalMode),
            storeAction: @js(route('icd10.store')),
            updateBase: @js(url('/icd10')),
            form: @js($modalForm),
            openCreate() {
                this.mode = 'create';
                this.form = {
                    id: null,
                    chapter_code: '',
                    code: '',
                    name_en: '',
                    name_id: '',
                    description: '',
                    is_active: true,
                };
                this.modalOpen = true;
            },
            openEdit(payload) {
                this.mode = 'update';
                this.form = payload;
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Master ICD-10</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Kelola daftar diagnosis standar WHO sebagai master pencarian dokter. Saat ini struktur mendukung kode, nama Inggris, dan nama Indonesia internal.
                    </p>
                </div>

                @if ($abilities['create'])
                    <x-ui.button type="button" x-on:click="openCreate()">Tambah ICD-10</x-ui.button>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('icd10') }}" class="grid gap-4 xl:grid-cols-[1.2fr_200px_180px_auto]">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari kode atau nama diagnosis" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Chapter</label>
                    <select name="chapter" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua chapter</option>
                        @foreach ($chapterOptions as $chapterOption)
                            <option value="{{ $chapterOption }}" @selected($filters['chapter'] === $chapterOption)>{{ $chapterOption }}</option>
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
                    <a href="{{ route('icd10') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">Reset</a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">ICD-10 list</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $codes->firstItem() ?? 0 }} - {{ $codes->lastItem() ?? 0 }} dari {{ $codes->total() }} kode.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Code</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">English</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Indonesia</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Used</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($codes as $code)
                            @php
                                $editPayload = [
                                    'id' => $code->id,
                                    'chapter_code' => $code->chapter_code,
                                    'code' => $code->code,
                                    'name_en' => $code->name_en,
                                    'name_id' => $code->name_id,
                                    'description' => $code->description,
                                    'is_active' => (bool) $code->is_active,
                                ];
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $code->code }}</p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $code->chapter_code ?: '-' }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $code->name_en }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $code->name_id }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $code->is_active ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">
                                        {{ $code->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $code->diagnoses_count }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'])
                                            <x-ui.icon-button type="button" title="Edit ICD-10" data-payload='@json($editPayload)' x-on:click='openEdit(JSON.parse($el.dataset.payload))'>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5V6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </x-ui.icon-button>
                                        @endif

                                        @if ($abilities['delete'])
                                            <form method="POST" action="{{ route('icd10.delete', $code) }}">
                                                @csrf
                                                <x-ui.icon-button type="submit" title="Hapus ICD-10" onclick="return confirm('Hapus kode ICD-10 ini?')">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                        <path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M9 11V17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M15 11V17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M7 7L8 19C8.04852 19.6027 8.55164 20.0673 9.15618 20.0673H14.8438C15.4484 20.0673 15.9515 19.6027 16 19L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                        <path d="M10 7V4.75C10 4.33579 10.3358 4 10.75 4H13.25C13.6642 4 14 4.33579 14 4.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
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
                                    Belum ada kode ICD-10 yang cocok dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                {{ $codes->links() }}
            </div>
        </section>

        <x-ui.modal show="modalOpen" maxWidth="3xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="mode === 'create' ? 'Tambah ICD-10' : 'Update ICD-10'"></h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Nama Inggris mengikuti kode WHO, nama Indonesia dipakai sebagai label operasional internal.</p>
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
                <input type="hidden" name="form_context" x-bind:value="mode === 'create' ? 'icd10-create' : 'icd10-update'">
                <input type="hidden" name="entity_id" x-bind:value="form.id">

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Chapter code</label>
                        <input x-model="form.chapter_code" type="text" name="chapter_code" placeholder="XVIII" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Code</label>
                        <input x-model="form.code" type="text" name="code" placeholder="J06.9" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm uppercase text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Name (English)</label>
                        <input x-model="form.name_en" type="text" name="name_en" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Name (Indonesia)</label>
                        <input x-model="form.name_id" type="text" name="name_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                        <textarea x-model="form.description" name="description" rows="3" class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                    </div>
                    <div class="flex items-end">
                        <div>
                            <input type="hidden" name="is_active" x-bind:value="form.is_active ? 1 : 0">
                            <label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input x-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                                <span>Kode aktif</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeModal()">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="mode === 'create' ? 'Simpan ICD-10' : 'Update ICD-10'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection

