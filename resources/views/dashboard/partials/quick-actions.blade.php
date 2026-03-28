<section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="flex flex-col gap-1">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Quick Actions</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Shortcut ke modul yang paling sering dipakai oleh role ini.
        </p>
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($quickActions as $action)
            <a
                href="{{ $action['route'] }}"
                class="group rounded-2xl border border-gray-200 bg-gradient-to-br {{ $action['accent'] }} px-4 py-4 transition hover:-translate-y-0.5 hover:border-brand-300 dark:border-gray-800"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $action['title'] }}</p>
                        <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $action['description'] }}</p>
                    </div>
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-white/80 bg-white/80 text-gray-700 dark:border-white/10 dark:bg-white/10 dark:text-gray-200">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M7 17L17 7M17 7H9M17 7V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </div>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-200 px-4 py-5 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                Belum ada shortcut khusus untuk role ini.
            </div>
        @endforelse
    </div>
</section>
