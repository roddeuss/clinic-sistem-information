<section class="overflow-hidden rounded-[28px] border border-gray-200 bg-gradient-to-br {{ $dashboardHeader['theme'] }} p-6 shadow-theme-sm dark:border-gray-800">
    <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
        <div>
            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] {{ $dashboardHeader['badge'] }}">
                {{ $dashboardHeader['eyebrow'] }}
            </span>
            <h1 class="mt-4 max-w-3xl text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                {{ $dashboardHeader['title'] }}
            </h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                {{ $dashboardHeader['description'] }}
            </p>

            <div class="mt-6 flex flex-wrap gap-3">
                @foreach ($dashboardContext['chips'] as $chip)
                    <div class="rounded-2xl border border-white/70 bg-white/80 px-4 py-3 shadow-theme-xs dark:border-white/10 dark:bg-white/5">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-400">{{ $chip['label'] }}</p>
                        <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ $chip['value'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="space-y-4">
            @if ($dashboardContext['showCounterSelector'] && $counterOptions->isNotEmpty())
                <div class="rounded-2xl border border-gray-200 bg-white/85 p-4 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900/80">
                    <div class="flex flex-col gap-1">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Counter Context</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Pilih counter aktif supaya branch focus di dashboard operasional tetap sinkron.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('active-counter.update') }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto]">
                        @csrf
                        <select
                            name="counter_id"
                            class="h-11 rounded-2xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden transition focus:border-brand-500 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            @foreach ($counterOptions as $counterOption)
                                <option value="{{ $counterOption->id }}" @selected($dashboardContext['activeCounter']?->id === $counterOption->id)>
                                    {{ $counterOption->branch?->code }} - {{ $counterOption->code }} {{ $counterOption->name }}
                                </option>
                            @endforeach
                        </select>
                        <button
                            type="submit"
                            class="inline-flex h-11 items-center justify-center rounded-2xl bg-brand-600 px-5 text-sm font-medium text-white transition hover:bg-brand-700"
                        >
                            Set counter
                        </button>
                    </form>
                </div>
            @endif

            <div class="rounded-2xl border border-gray-200 bg-white/85 p-4 shadow-theme-xs dark:border-gray-800 dark:bg-gray-900/80">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Hari Ini</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $dashboardContext['today_label'] }}</p>
                    </div>
                    <a
                        href="{{ $notificationsPanel['route'] }}"
                        class="inline-flex rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-semibold text-gray-700 transition hover:border-brand-300 hover:text-brand-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-brand-500/30 dark:hover:text-brand-300"
                    >
                        {{ $notificationsPanel['unreadCount'] }} unread
                    </a>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <a href="{{ route('queues') }}" class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 transition hover:border-brand-300 hover:bg-brand-50/60 dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-500/30 dark:hover:bg-white/[0.05]">
                        <p class="text-xs uppercase tracking-[0.18em] text-gray-400">Queue Desk</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">Kontrol panggilan pasien</p>
                    </a>
                    <a href="{{ route('notifications') }}" class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 transition hover:border-brand-300 hover:bg-brand-50/60 dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-500/30 dark:hover:bg-white/[0.05]">
                        <p class="text-xs uppercase tracking-[0.18em] text-gray-400">Alerts</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">Lihat semua notifikasi</p>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
