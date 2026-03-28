@extends('layouts.app')

@section('content')
    <div
        x-data="window.queueBoardHelpers.queueDesk({
            branchChannel: @js($realtime['branch_channel']),
            boardEndpoint: @js(route('queues.board')),
            watchedDate: @js($queueDate),
            initialLiveBoard: @js($liveBoard),
            initialSectionSummaries: @js($sectionSummaries),
            activeCounter: @js($activeCounter),
        })"
        x-init="init()"
        class="space-y-6"
    >
        <section class="rounded-3xl border border-gray-200 bg-white p-8 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Queue Display</h1>
                        <span x-show="liveConnected" x-cloak class="inline-flex rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700 dark:bg-green-500/10 dark:text-green-300">
                            Reverb live
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        <span x-text="activeCounter ? `${activeCounter.branch?.code ?? ''} - ${activeCounter.branch?.name ?? ''}` : 'Branch belum dipilih'"></span>
                        | {{ \Illuminate\Support\Carbon::parse($queueDate)->format('d M Y') }}
                    </p>
                    <p class="mt-1 text-xs text-gray-400" x-show="lastSyncedAt" x-cloak>
                        Last sync <span x-text="lastSyncedAt"></span>
                    </p>
                </div>
                <a href="{{ route('queues', ['date' => $queueDate]) }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                    Back to Queue Desk
                </a>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.2fr_1fr]">
            <div class="rounded-3xl border border-gray-200 bg-white p-8 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Now Calling</h2>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Tampilan realtime untuk layar tunggu, lengkap dengan dokter dan room.</p>
                    </div>
                    <span class="inline-flex rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                        Live Board
                    </span>
                </div>

                <div class="mt-6 grid gap-5">
                    <template x-if="liveBoard.length === 0">
                        <div class="rounded-3xl border border-dashed border-gray-300 px-8 py-14 text-center text-base text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            Belum ada pasien yang sedang dipanggil atau dilayani.
                        </div>
                    </template>

                    <template x-for="boardItem in liveBoard" :key="boardItem.id">
                        <div class="rounded-3xl border border-gray-200 bg-gray-50 p-7 dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="flex flex-wrap items-start justify-between gap-6">
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-gray-500" x-text="`${safe(boardItem.section_type, 'regular').toUpperCase()} | ${safe(boardItem.section_name)}`"></p>
                                    <p class="mt-4 text-5xl font-semibold tracking-tight text-gray-900 dark:text-white" x-text="safe(boardItem.queue_code)"></p>
                                    <p class="mt-4 text-lg text-gray-700 dark:text-gray-300" x-text="safe(boardItem.doctor_name, 'Doctor belum dipilih')"></p>
                                    <p class="mt-2 text-sm text-gray-400" x-text="safe(boardItem.room_label, 'Room belum diatur')"></p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex rounded-full px-3 py-1 text-sm font-medium" x-bind:class="boardStatusClass(boardItem.status)" x-text="formatStatus(boardItem.status)"></span>
                                    <p class="mt-4 text-sm text-gray-400" x-text="`${safe(boardItem.counter_code)} ${boardItem.counter_name || ''}`.trim()"></p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="rounded-3xl border border-gray-200 bg-white p-8 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Section Summary</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Ringkasan antrean yang menunggu, sedang dipanggil, dan dilayani per section.</p>
                </div>

                <div class="mt-6 space-y-4">
                    <template x-if="sectionSummaries.length === 0">
                        <div class="rounded-3xl border border-dashed border-gray-300 px-8 py-14 text-center text-base text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            Belum ada summary queue untuk branch ini.
                        </div>
                    </template>

                    <template x-for="summary in sectionSummaries" :key="summary.section_id">
                        <div class="rounded-3xl border border-gray-200 p-5 dark:border-gray-800">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white" x-text="safe(summary.section_name)"></p>
                                    <p class="mt-1 text-xs uppercase tracking-[0.16em] text-gray-400" x-text="safe(summary.section_type, 'regular').toUpperCase()"></p>
                                </div>
                                <div class="text-right text-xs text-gray-400">
                                    <div x-text="`Current ${safe(summary.current_queue_code)}`"></div>
                                    <div class="mt-1" x-text="safe(summary.current_doctor_name, 'Doctor belum dipilih')"></div>
                                    <div class="mt-1" x-text="safe(summary.current_room_label, 'Room belum diatur')"></div>
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-4 gap-3 text-center">
                                <div class="rounded-2xl bg-gray-50 px-3 py-4 dark:bg-white/[0.03]">
                                    <div class="text-xl font-semibold text-gray-900 dark:text-white" x-text="summary.waiting_count"></div>
                                    <div class="mt-1 text-[11px] uppercase tracking-[0.16em] text-gray-400">Waiting</div>
                                </div>
                                <div class="rounded-2xl bg-amber-50 px-3 py-4 dark:bg-amber-500/10">
                                    <div class="text-xl font-semibold text-amber-700 dark:text-amber-300" x-text="summary.called_count"></div>
                                    <div class="mt-1 text-[11px] uppercase tracking-[0.16em] text-amber-500 dark:text-amber-200">Called</div>
                                </div>
                                <div class="rounded-2xl bg-green-50 px-3 py-4 dark:bg-green-500/10">
                                    <div class="text-xl font-semibold text-green-700 dark:text-green-300" x-text="summary.in_service_count"></div>
                                    <div class="mt-1 text-[11px] uppercase tracking-[0.16em] text-green-500 dark:text-green-200">Serving</div>
                                </div>
                                <div class="rounded-2xl bg-brand-50 px-3 py-4 dark:bg-brand-500/10">
                                    <div class="text-sm font-semibold text-brand-700 dark:text-brand-300" x-text="safe(summary.next_queue_code)"></div>
                                    <div class="mt-1 text-[11px] uppercase tracking-[0.16em] text-brand-500 dark:text-brand-200">Next</div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>
    </div>
@endsection

@include('modules.queues._realtime-script')
