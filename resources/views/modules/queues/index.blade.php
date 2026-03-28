@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Queues" />

    <div
        x-data="window.queueBoardHelpers.queueDesk({
            branchChannel: @js($realtime['branch_channel']),
            boardEndpoint: @js(route('queues.board')),
            watchedDate: @js($realtime['watched_date']),
            initialLiveBoard: @js($liveBoard),
            initialSectionSummaries: @js($sectionSummaries),
            activeCounter: @js($activeCounter),
        })"
        x-init="init()"
        class="space-y-6"
    >
        @if (session('status'))
            <x-ui.alert variant="success" :message="session('status')" />
        @endif

        @if ($errors->any())
            <x-ui.alert variant="error" :message="$errors->first()" />
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Realtime queue desk</h1>
                        <span
                            x-show="liveConnected"
                            x-cloak
                            class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700 dark:bg-green-500/10 dark:text-green-300"
                        >
                            Reverb live
                        </span>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Panggil antrian per section dari counter aktif. Board akan auto-sync via Reverb, lengkap dengan dokter dan room yang sedang dipanggil.
                    </p>
                    <p class="mt-2 text-xs text-gray-400" x-show="lastSyncedAt" x-cloak>
                        Last sync <span x-text="lastSyncedAt"></span>
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('active-counter.update') }}" class="grid gap-3 sm:grid-cols-[280px_auto]">
                        @csrf
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Counter aktif</label>
                            <select name="counter_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                <option value="">Pilih counter</option>
                                @foreach ($counterOptions as $counterOption)
                                    <option value="{{ $counterOption->id }}" @selected($activeCounter?->id === $counterOption->id)>{{ $counterOption->branch?->code }} - {{ $counterOption->code }} {{ $counterOption->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end">
                            <x-ui.button type="submit" variant="outline">Set Counter</x-ui.button>
                        </div>
                    </form>
                    <div class="flex items-end">
                        <a href="{{ route('queues.display', ['date' => $filters['date']]) }}" target="_blank" class="inline-flex h-11 items-center rounded-xl border border-brand-200 bg-brand-50 px-4 text-sm font-medium text-brand-700 transition hover:bg-brand-100 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-300">
                            Open Display
                        </a>
                    </div>
                </div>
            </div>

            @if ($activeCounter)
                <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-500/20 dark:bg-green-500/10 dark:text-green-100">
                    Counter aktif: <span class="font-medium">{{ $activeCounter->code }} - {{ $activeCounter->name }}</span> | Branch {{ $activeCounter->branch?->code }} - {{ $activeCounter->branch?->name }}
                </div>
            @else
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
                    Pilih counter aktif terlebih dahulu sebelum memanggil antrian dan mengaktifkan broadcast realtime.
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="grid gap-4 xl:grid-cols-[1.1fr_1.2fr]">
                <form method="POST" action="{{ route('queues.call-next') }}" class="grid gap-4 md:grid-cols-[1fr_auto]">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Call next per section</label>
                        <select name="section_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Pilih section</option>
                            @foreach ($sectionOptions as $sectionOption)
                                <option value="{{ $sectionOption->id }}">{{ strtoupper($sectionOption->type) }} | {{ $sectionOption->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        @if ($abilities['call_next'])
                            <x-ui.button type="submit">Panggil Berikutnya</x-ui.button>
                        @endif
                    </div>
                </form>

                <form method="GET" action="{{ route('queues') }}" class="grid gap-4 md:grid-cols-[1.1fr_180px_220px_220px_220px_160px_auto]">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                        <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari queue, pasien, RM, dokter" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal</label>
                        <input type="date" name="date" value="{{ $filters['date'] }}" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <select name="status" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Semua status</option>
                            @foreach (['waiting','called','in_service','completed','skipped','cancelled'] as $status)
                                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Section</label>
                        <select name="section" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Semua section</option>
                            @foreach ($sectionOptions as $sectionOption)
                                <option value="{{ $sectionOption->id }}" @selected($filters['section'] === (string) $sectionOption->id)>{{ $sectionOption->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Sort</label>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <select name="sort_by" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                @foreach ($sortOptions as $sortKey => $sortLabel)
                                    <option value="{{ $sortKey }}" @selected($filters['sort_by'] === $sortKey)>{{ $sortLabel }}</option>
                                @endforeach
                            </select>
                            <select name="sort_direction" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                <option value="desc" @selected($filters['sort_direction'] === 'desc')>Desc</option>
                                <option value="asc" @selected($filters['sort_direction'] === 'asc')>Asc</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Per page</label>
                        <select name="per_page" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($perPageOptions as $option)
                                <option value="{{ $option }}" @selected($filters['per_page'] === $option)>{{ $option }} rows</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-3">
                        <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
                        <a href="{{ route('queues') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">Reset</a>
                    </div>
                </form>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.15fr_1fr]">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Live queue board</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan antrian yang sedang dipanggil atau sedang dilayani pada tanggal filter aktif.</p>
                    </div>
                    <span class="inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">{{ \Illuminate\Support\Carbon::parse($filters['date'])->format('d M Y') }}</span>
                </div>

                <div class="mt-5 grid gap-4">
                    <template x-if="liveBoard.length === 0">
                        <div class="rounded-2xl border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            Belum ada antrian yang sedang dipanggil atau dilayani untuk tanggal ini.
                        </div>
                    </template>

                    <template x-for="boardItem in liveBoard" :key="boardItem.id">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500" x-text="`${safe(boardItem.section_type, 'regular').toUpperCase()} | ${safe(boardItem.section_name)}`"></p>
                                    <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white" x-text="safe(boardItem.queue_code)"></p>
                                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300" x-text="safe(boardItem.doctor_name, 'Doctor belum dipilih')"></p>
                                    <p class="mt-1 text-xs text-gray-400" x-text="safe(boardItem.room_label, 'Room belum diatur')"></p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" x-bind:class="boardStatusClass(boardItem.status)" x-text="formatStatus(boardItem.status)"></span>
                                    <p class="mt-3 text-xs text-gray-400" x-text="`${safe(boardItem.counter_code)} ${boardItem.counter_name || ''}`.trim()"></p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Section queue summary</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ringkasan waiting, called, in service, dan next queue per section pada branch aktif.</p>
                </div>

                <div class="mt-5 space-y-4">
                    <template x-if="sectionSummaries.length === 0">
                        <div class="rounded-2xl border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            Belum ada ringkasan queue karena branch aktif belum dipilih atau belum ada data antrian.
                        </div>
                    </template>

                    <template x-for="summary in sectionSummaries" :key="summary.section_id">
                        <div class="rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
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
                                <div class="rounded-xl bg-gray-50 px-3 py-3 dark:bg-white/[0.03]">
                                    <div class="text-lg font-semibold text-gray-900 dark:text-white" x-text="summary.waiting_count"></div>
                                    <div class="mt-1 text-[11px] uppercase tracking-[0.16em] text-gray-400">Waiting</div>
                                </div>
                                <div class="rounded-xl bg-amber-50 px-3 py-3 dark:bg-amber-500/10">
                                    <div class="text-lg font-semibold text-amber-700 dark:text-amber-300" x-text="summary.called_count"></div>
                                    <div class="mt-1 text-[11px] uppercase tracking-[0.16em] text-amber-500 dark:text-amber-200">Called</div>
                                </div>
                                <div class="rounded-xl bg-green-50 px-3 py-3 dark:bg-green-500/10">
                                    <div class="text-lg font-semibold text-green-700 dark:text-green-300" x-text="summary.in_service_count"></div>
                                    <div class="mt-1 text-[11px] uppercase tracking-[0.16em] text-green-500 dark:text-green-200">Serving</div>
                                </div>
                                <div class="rounded-xl bg-brand-50 px-3 py-3 dark:bg-brand-500/10">
                                    <div class="text-sm font-semibold text-brand-700 dark:text-brand-300" x-text="safe(summary.next_queue_code)"></div>
                                    <div class="mt-1 text-[11px] uppercase tracking-[0.16em] text-brand-500 dark:text-brand-200">Next</div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Queue list</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $queueTickets->firstItem() ?? 0 }} - {{ $queueTickets->lastItem() ?? 0 }} dari {{ $queueTickets->total() }} antrian.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Queue</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Patient</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Section / Doctor</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Called By</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($queueTickets as $queueTicket)
                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $queueTicket->queue_code }}</p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $queueTicket->queue_date?->format('d M Y') }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $queueTicket->patient?->full_name }}</div>
                                    <div class="mt-1">{{ $queueTicket->patientBranchRecord?->medical_record_no }}</div>
                                    <div class="mt-1">{{ $queueTicket->patient?->phone }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $queueTicket->section?->name }}</div>
                                    <div class="mt-1">{{ $queueTicket->doctor?->displayName() ?? 'Doctor belum dipilih' }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $queueTicket->visitRegistration?->doctorSchedule?->room_label ?: 'Room belum diatur' }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $queueTicket->calledBy?->name ?: '-' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-700 dark:bg-white/[0.04] dark:text-gray-200">
                                        {{ strtoupper(str_replace('_', ' ', $queueTicket->status)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'])
                                            @foreach ([
                                                'call' => ['waiting', 'skipped'],
                                                'serve' => ['called'],
                                                'complete' => ['called', 'in_service'],
                                                'skip' => ['waiting', 'called'],
                                                'cancel' => ['waiting', 'called', 'in_service', 'skipped'],
                                            ] as $action => $allowedStatuses)
                                                @if (in_array($queueTicket->status, $allowedStatuses, true))
                                                    <form method="POST" action="{{ route('queues.action', $queueTicket) }}">
                                                        @csrf
                                                        <input type="hidden" name="action" value="{{ $action }}">
                                                        <x-ui.button type="submit" size="sm" variant="{{ $action === 'cancel' ? 'outline' : 'primary' }}">{{ strtoupper($action) }}</x-ui.button>
                                                    </form>
                                                @endif
                                            @endforeach
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada data antrian yang cocok dengan filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $queueTickets->links() }}</div>
        </section>
    </div>
@endsection

@include('modules.queues._realtime-script')
