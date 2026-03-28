<section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $panel['title'] }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $panel['description'] }}</p>
        </div>
        <a href="{{ $panel['route'] }}" class="inline-flex rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:border-brand-300 hover:text-brand-700 dark:border-gray-700 dark:text-gray-300 dark:hover:border-brand-500/30 dark:hover:text-brand-300">
            {{ $panel['unreadCount'] }} unread
        </a>
    </div>

    <div class="mt-5 space-y-3">
        @forelse ($panel['items'] as $item)
            <a
                href="{{ $item['route'] }}"
                class="block rounded-2xl border border-gray-200 px-4 py-4 transition hover:border-brand-300 hover:bg-brand-50/40 dark:border-gray-800 dark:hover:border-brand-500/30 dark:hover:bg-white/[0.03]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $item['title'] }}</p>
                            @if ($item['isUnread'])
                                <span class="inline-flex h-2.5 w-2.5 rounded-full bg-brand-500"></span>
                            @endif
                        </div>
                        <p class="mt-1 text-xs uppercase tracking-[0.16em] text-gray-400">{{ $item['module'] }}</p>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $item['message'] }}</p>
                    </div>
                    <span class="shrink-0 text-xs text-gray-400">{{ $item['time'] }}</span>
                </div>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-200 px-4 py-5 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                Belum ada notifikasi baru untuk akun ini.
            </div>
        @endforelse
    </div>
</section>
