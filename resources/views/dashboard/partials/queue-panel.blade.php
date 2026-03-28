<section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $panel['title'] }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $panel['description'] }}</p>
            @if ($panel['branchLabel'])
                <p class="mt-2 text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">{{ $panel['branchLabel'] }}</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ $panel['deskRoute'] }}" class="inline-flex rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:border-brand-300 hover:text-brand-700 dark:border-gray-700 dark:text-gray-300 dark:hover:border-brand-500/30 dark:hover:text-brand-300">
                Queue desk
            </a>
            <a href="{{ $panel['displayRoute'] }}" class="inline-flex rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:border-brand-300 hover:text-brand-700 dark:border-gray-700 dark:text-gray-300 dark:hover:border-brand-500/30 dark:hover:text-brand-300">
                Display board
            </a>
        </div>
    </div>

    @if (filled($panel['liveBoard']))
        <div class="mt-5">
            <div class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Live board</div>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($panel['liveBoard'] as $live)
                    <div class="rounded-2xl border border-brand-100 bg-brand-50/60 px-4 py-4 dark:border-brand-500/20 dark:bg-brand-500/5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $live['queue_code'] ?? '-' }}</p>
                                <p class="mt-1 text-sm font-medium text-gray-700 dark:text-gray-200">{{ $live['section_name'] ?? '-' }}</p>
                            </div>
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-brand-700 dark:bg-white/10 dark:text-brand-300">
                                {{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $live['status'] ?? '')) }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                            {{ $live['doctor_name'] ?? 'Doctor belum dipilih' }}
                            @if (! empty($live['room_label']))
                                | {{ $live['room_label'] }}
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-5">
        <div class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Section summary</div>
        @if (filled($panel['sectionSummaries']))
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($panel['sectionSummaries'] as $summary)
                    <div class="rounded-2xl border border-gray-200 px-4 py-4 dark:border-gray-800">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $summary['section_name'] }}</p>
                                <p class="mt-1 text-xs uppercase tracking-[0.16em] text-gray-400">{{ $summary['section_type'] }}</p>
                            </div>
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-white/[0.06] dark:text-gray-300">
                                {{ $summary['waiting_count'] }} waiting
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-xl bg-gray-50 px-3 py-3 dark:bg-white/[0.03]">
                                <div class="text-gray-400">Current</div>
                                <div class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $summary['current_queue_code'] ?? '-' }}</div>
                            </div>
                            <div class="rounded-xl bg-gray-50 px-3 py-3 dark:bg-white/[0.03]">
                                <div class="text-gray-400">Next</div>
                                <div class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $summary['next_queue_code'] ?? '-' }}</div>
                            </div>
                        </div>

                        @if ($summary['current_doctor_name'] || $summary['current_room_label'])
                            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $summary['current_doctor_name'] ?? 'Doctor belum dipilih' }}
                                @if ($summary['current_room_label'])
                                    | {{ $summary['current_room_label'] }}
                                @endif
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-gray-200 px-4 py-5 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                {{ $panel['emptyMessage'] }}
            </div>
        @endif
    </div>
</section>
