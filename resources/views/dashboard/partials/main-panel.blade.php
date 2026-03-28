<section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="flex flex-col gap-1">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $panel['title'] }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $panel['description'] }}</p>
    </div>

    <div class="mt-5 space-y-3">
        @forelse ($panel['items'] as $item)
            <a
                href="{{ $item['route'] }}"
                class="flex items-start justify-between gap-4 rounded-2xl border border-gray-200 px-4 py-4 transition hover:border-brand-300 hover:bg-brand-50/40 dark:border-gray-800 dark:hover:border-brand-500/30 dark:hover:bg-white/[0.03]"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $item['title'] }}</p>
                    <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $item['meta'] }}</p>
                </div>
                @php
                    $toneClasses = match ($item['status']['tone']) {
                        'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                        'success' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
                        'danger' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
                        'info' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                    };
                @endphp
                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold {{ $toneClasses }}">
                    {{ $item['status']['label'] }}
                </span>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-200 px-4 py-5 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
                {{ $panel['emptyMessage'] }}
            </div>
        @endforelse
    </div>
</section>
