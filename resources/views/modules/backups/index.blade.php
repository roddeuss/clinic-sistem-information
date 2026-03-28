@php
    $createModalOpen = $errors->any() && old('form_context') === 'backup-create';
    $restoreModalOpen = $errors->any() && old('form_context') === 'backup-restore';
    $archiveModalOpen = $errors->any() && old('form_context') === 'backup-archive';
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Backup Center" />

    <div
        x-data="{
            createModalOpen: @js($createModalOpen),
            detailModalOpen: false,
            restoreModalOpen: @js($restoreModalOpen),
            archiveModalOpen: @js($archiveModalOpen),
            detail: null,
            restoreForm: {
                id: @js(old('entity_id')),
                backup_no: @js(old('entity_label', '')),
                restore_reason: @js(old('restore_reason', '')),
            },
            archiveForm: {
                id: @js(old('entity_id')),
                backup_no: @js(old('entity_label', '')),
                archive_reason: @js(old('archive_reason', '')),
            },
            openDetail(payload) {
                this.detail = payload;
                this.detailModalOpen = true;
            },
            openRestore(payload) {
                this.restoreForm = {
                    id: payload.id,
                    backup_no: payload.backup_no,
                    restore_reason: '',
                };
                this.restoreModalOpen = true;
            },
            openArchive(payload) {
                this.archiveForm = {
                    id: payload.id,
                    backup_no: payload.backup_no,
                    archive_reason: '',
                };
                this.archiveModalOpen = true;
            },
        }"
        @keydown.escape.window="createModalOpen = false; detailModalOpen = false; restoreModalOpen = false; archiveModalOpen = false"
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Backup database PostgreSQL</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Modul ini dipakai untuk membuat backup database manual, mengunduh arsip ZIP, dan merestore database penuh saat diperlukan.
                    </p>
                </div>

                @if ($abilities['create'])
                    <x-ui.button type="button" x-on:click="createModalOpen = true">Buat Backup</x-ui.button>
                @endif
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total backup</p>
                <p class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($summary['total']) }}</p>
            </div>
            <div class="rounded-2xl border border-green-200 bg-green-50 p-5 shadow-theme-sm dark:border-green-500/20 dark:bg-green-500/10">
                <p class="text-sm font-medium text-green-700 dark:text-green-300">Ready</p>
                <p class="mt-3 text-2xl font-semibold text-green-800 dark:text-green-200">{{ number_format($summary['ready']) }}</p>
            </div>
            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-theme-sm dark:border-blue-500/20 dark:bg-blue-500/10">
                <p class="text-sm font-medium text-blue-700 dark:text-blue-300">Restored</p>
                <p class="mt-3 text-2xl font-semibold text-blue-800 dark:text-blue-200">{{ number_format($summary['restored']) }}</p>
            </div>
            <div class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-theme-sm dark:border-red-500/20 dark:bg-red-500/10">
                <p class="text-sm font-medium text-red-700 dark:text-red-300">Failed</p>
                <p class="mt-3 text-2xl font-semibold text-red-800 dark:text-red-200">{{ number_format($summary['failed']) }}</p>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('backups') }}" class="grid gap-4 md:grid-cols-[1fr_180px_auto]">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input
                        type="text"
                        name="search"
                        value="{{ $filters['search'] }}"
                        placeholder="Cari nomor backup atau nama file"
                        class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    >
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <select name="status" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua status</option>
                        <option value="processing" @selected($filters['status'] === 'processing')>Processing</option>
                        <option value="ready" @selected($filters['status'] === 'ready')>Ready</option>
                        <option value="failed" @selected($filters['status'] === 'failed')>Failed</option>
                        <option value="restored" @selected($filters['status'] === 'restored')>Restored</option>
                    </select>
                </div>
                <div class="flex items-end gap-3">
                    <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
                    <a href="{{ route('backups') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Daftar backup</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Menampilkan {{ $backups->firstItem() ?? 0 }} - {{ $backups->lastItem() ?? 0 }} dari {{ $backups->total() }} backup.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Backup</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">File</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Created</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($backups as $backup)
                            @php
                                $sizeLabel = match (true) {
                                    ! $backup->file_size_bytes => '-',
                                    $backup->file_size_bytes >= 1048576 => number_format($backup->file_size_bytes / 1048576, 2) . ' MB',
                                    $backup->file_size_bytes >= 1024 => number_format($backup->file_size_bytes / 1024, 2) . ' KB',
                                    default => number_format($backup->file_size_bytes) . ' B',
                                };

                                $statusClasses = match ($backup->status) {
                                    'ready' => 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300',
                                    'restored' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300',
                                    'failed' => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-300',
                                    default => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                                };

                                $backupPayload = [
                                    'id' => $backup->id,
                                    'backup_no' => $backup->backup_no,
                                    'backup_type' => str($backup->backup_type)->headline()->toString(),
                                    'dump_format' => strtoupper($backup->dump_format),
                                    'status' => str($backup->status)->headline()->toString(),
                                    'file_name' => $backup->file_name ?: '-',
                                    'file_size' => $sizeLabel,
                                    'notes' => $backup->notes ?: '-',
                                    'failure_reason' => $backup->failure_reason ?: '-',
                                    'created_by' => $backup->createdBy?->name ?: '-',
                                    'created_at' => $backup->created_at?->format('d M Y H:i') ?: '-',
                                    'restored_by' => $backup->restoredBy?->name ?: '-',
                                    'restored_at' => $backup->restored_at?->format('d M Y H:i') ?: '-',
                                ];
                                $backupPayloadJson = e(json_encode($backupPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP));
                            @endphp

                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $backup->backup_no }}</div>
                                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ str($backup->backup_type)->headline() }} - {{ strtoupper($backup->dump_format) }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $backup->file_name ?: '-' }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $sizeLabel }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $backup->createdBy?->name ?: '-' }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $backup->created_at?->format('d M Y H:i') ?: '-' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses }}">
                                        {{ str($backup->status)->headline() }}
                                    </span>
                                    @if ($backup->restored_at)
                                        <div class="mt-1 text-xs text-gray-400">Restored {{ $backup->restored_at->format('d M Y H:i') }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        <x-ui.icon-button
                                            type="button"
                                            title="Detail"
                                            data-payload="{{ $backupPayloadJson }}"
                                            x-on:click="openDetail(JSON.parse($el.dataset.payload))"
                                        >
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                <path d="M12 16V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                <path d="M12 8H12.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                <path d="M3 12C4.8 7.5 8 5.25 12 5.25C16 5.25 19.2 7.5 21 12C19.2 16.5 16 18.75 12 18.75C8 18.75 4.8 16.5 3 12Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                            </svg>
                                        </x-ui.icon-button>

                                        @if ($abilities['download'] && $backup->file_path)
                                            <a href="{{ route('backups.download', $backup) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white" title="Download backup">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M12 4V14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    <path d="M8.5 10.5L12 14L15.5 10.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    <path d="M5 18H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                            </a>
                                        @endif

                                        @if ($abilities['restore'] && in_array($backup->status, ['ready', 'restored'], true) && $backup->file_path)
                                            <x-ui.icon-button
                                                type="button"
                                                title="Restore backup"
                                                data-payload="{{ $backupPayloadJson }}"
                                                x-on:click="openRestore(JSON.parse($el.dataset.payload))"
                                            >
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M4 12C4 7.58172 7.58172 4 12 4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20C9.65499 20 7.5457 18.9896 6.08106 17.3799" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    <path d="M4 7V12H9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </x-ui.icon-button>
                                        @endif

                                        @if ($abilities['delete'])
                                            <x-ui.icon-button
                                                type="button"
                                                variant="danger"
                                                title="Arsipkan backup"
                                                data-payload="{{ $backupPayloadJson }}"
                                                x-on:click="openArchive(JSON.parse($el.dataset.payload))"
                                            >
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    <path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    <path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    <path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    <path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                            </x-ui.icon-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada backup database.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                {{ $backups->links() }}
            </div>
        </section>

        <x-ui.modal show="detailModalOpen" maxWidth="3xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Detail backup</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ringkasan file dan histori restore backup terpilih.</p>
                    </div>
                    <x-ui.icon-button title="Tutup modal" x-on:click="detailModalOpen = false">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </x-ui.icon-button>
                </div>
            </div>
            <div class="space-y-5 p-6" x-show="detail">
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Backup number</p>
                        <p class="mt-2 text-sm text-gray-900 dark:text-white" x-text="detail?.backup_no || '-'"></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</p>
                        <p class="mt-2 text-sm text-gray-900 dark:text-white" x-text="detail?.status || '-'"></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Format</p>
                        <p class="mt-2 text-sm text-gray-900 dark:text-white" x-text="`${detail?.backup_type || '-'} - ${detail?.dump_format || '-'}`"></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">File</p>
                        <p class="mt-2 text-sm text-gray-900 dark:text-white" x-text="`${detail?.file_name || '-'} (${detail?.file_size || '-'})`"></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Created by</p>
                        <p class="mt-2 text-sm text-gray-900 dark:text-white" x-text="detail?.created_by || '-'"></p>
                        <p class="mt-1 text-xs text-gray-400" x-text="detail?.created_at || '-'"></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Last restored</p>
                        <p class="mt-2 text-sm text-gray-900 dark:text-white" x-text="detail?.restored_by || '-'"></p>
                        <p class="mt-1 text-xs text-gray-400" x-text="detail?.restored_at || '-'"></p>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Notes</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300" x-text="detail?.notes || '-'"></p>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Failure reason</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300" x-text="detail?.failure_reason || '-'"></p>
                </div>
            </div>
        </x-ui.modal>

        <x-ui.modal show="createModalOpen" maxWidth="2xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Buat backup database</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Sistem akan membuat dump PostgreSQL lalu membungkusnya dalam arsip ZIP.</p>
                    </div>
                    <x-ui.icon-button title="Tutup modal" x-on:click="createModalOpen = false">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </x-ui.icon-button>
                </div>
            </div>
            <form method="POST" action="{{ route('backups.store') }}" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="backup-create">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Catatan</label>
                    <textarea name="notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white" placeholder="Catatan opsional untuk backup manual ini">{{ old('notes') }}</textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="createModalOpen = false">Batal</x-ui.button>
                    <x-ui.button type="submit">Buat Backup</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="restoreModalOpen" maxWidth="2xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Restore backup</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Restore akan menimpa database aktif. Gunakan hanya saat benar-benar diperlukan.</p>
                    </div>
                    <x-ui.icon-button title="Tutup modal" x-on:click="restoreModalOpen = false">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </x-ui.icon-button>
                </div>
            </div>
            <form method="POST" x-bind:action="restoreForm.id ? `/backups/${restoreForm.id}/restore` : '#'" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="backup-restore">
                <input type="hidden" name="entity_id" x-bind:value="restoreForm.id">
                <input type="hidden" name="entity_label" x-bind:value="restoreForm.backup_no">

                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                    Backup yang akan direstore:
                    <span class="font-semibold" x-text="restoreForm.backup_no || '-'"></span>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alasan restore</label>
                    <textarea x-model="restoreForm.restore_reason" name="restore_reason" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white" placeholder="Tuliskan alasan restore dan dampaknya"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="restoreModalOpen = false">Batal</x-ui.button>
                    <x-ui.button type="submit">Restore Backup</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="archiveModalOpen" maxWidth="2xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Arsipkan backup</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Backup akan disembunyikan dari daftar aktif, tetapi file fisiknya tetap tersimpan.</p>
                    </div>
                    <x-ui.icon-button title="Tutup modal" x-on:click="archiveModalOpen = false">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </x-ui.icon-button>
                </div>
            </div>
            <form method="POST" x-bind:action="archiveForm.id ? `/backups/${archiveForm.id}/delete` : '#'" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="backup-archive">
                <input type="hidden" name="entity_id" x-bind:value="archiveForm.id">
                <input type="hidden" name="entity_label" x-bind:value="archiveForm.backup_no">

                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300">
                    Backup yang akan diarsipkan:
                    <span class="font-semibold" x-text="archiveForm.backup_no || '-'"></span>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alasan arsip</label>
                    <textarea x-model="archiveForm.archive_reason" name="archive_reason" rows="4" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white" placeholder="Catatan opsional kenapa backup diarsipkan"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="archiveModalOpen = false">Batal</x-ui.button>
                    <x-ui.button type="submit" variant="danger">Arsipkan</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection
