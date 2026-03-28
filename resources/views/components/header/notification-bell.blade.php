<div
    class="relative"
    x-data="{ open: false }"
    @click.away="open = false"
>
    <button
        type="button"
        @click="open = !open"
        class="relative flex h-11 w-11 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        aria-label="Notifications"
    >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
            <path d="M6.5 9.75C6.5 6.8505 8.8505 4.5 11.75 4.5C14.6495 4.5 17 6.8505 17 9.75V12.6167C17 13.2646 17.2142 13.8944 17.6094 14.4078L18.75 15.8889V16.5H4.75V15.8889L5.89061 14.4078C6.28578 13.8944 6.5 13.2646 6.5 12.6167V9.75Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
            <path d="M9.5 19C10.0273 19.6214 10.8409 20 11.75 20C12.6591 20 13.4727 19.6214 14 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        @if ($unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-[11px] font-semibold text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div
        x-show="open"
        x-transition
        class="absolute right-0 z-50 mt-3 w-[360px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark"
        style="display: none;"
    >
        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
            <div>
                <div class="text-sm font-semibold text-gray-900 dark:text-white">Notifications</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $unreadCount }} unread</div>
            </div>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="text-xs font-medium text-brand-600 hover:text-brand-700 dark:text-brand-300 dark:hover:text-brand-200">Mark all read</button>
                </form>
            @endif
        </div>

        <div class="max-h-[420px] overflow-y-auto">
            @forelse ($recentNotifications as $notification)
                @php
                    $title = data_get($notification->data, 'title', 'System alert');
                    $message = data_get($notification->data, 'message', '');
                    $module = data_get($notification->data, 'module', 'system');
                @endphp
                <a
                    href="{{ route('notifications.open', $notification) }}"
                    class="block border-b border-gray-100 px-4 py-3 transition last:border-b-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.03]"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $title }}</span>
                                @if ($notification->read_at === null)
                                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-brand-500"></span>
                                @endif
                            </div>
                            <div class="mt-1 text-xs uppercase tracking-[0.12em] text-gray-400">{{ str_replace('_', ' ', $module) }}</div>
                            <p class="mt-1 text-sm leading-5 text-gray-500 dark:text-gray-400">{{ $message }}</p>
                            <div class="mt-2 text-xs text-gray-400">{{ $notification->created_at?->diffForHumans() }}</div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    Belum ada notifikasi.
                </div>
            @endforelse
        </div>

        <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-800">
            <a href="{{ route('notifications') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-300 dark:hover:text-brand-200">Lihat semua notifikasi</a>
        </div>
    </div>
</div>
