<section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="flex flex-col gap-1">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $panel['title'] }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $panel['description'] }}</p>
    </div>

    <div class="mt-5 space-y-3">
        @foreach ($panel['items'] as $item)
            @php
                $ringClasses = match ($item['tone']) {
                    'warning' => 'border-amber-200 bg-amber-50/70 dark:border-amber-500/20 dark:bg-amber-500/5',
                    'success' => 'border-emerald-200 bg-emerald-50/70 dark:border-emerald-500/20 dark:bg-emerald-500/5',
                    'danger' => 'border-rose-200 bg-rose-50/70 dark:border-rose-500/20 dark:bg-rose-500/5',
                    default => 'border-sky-200 bg-sky-50/70 dark:border-sky-500/20 dark:bg-sky-500/5',
                };
            @endphp
            <a
                href="{{ $item['route'] }}"
                class="block rounded-2xl border px-4 py-4 transition hover:-translate-y-0.5 {{ $ringClasses }}"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $item['title'] }}</p>
                        <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $item['description'] }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $item['value'] }}</div>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</section>
