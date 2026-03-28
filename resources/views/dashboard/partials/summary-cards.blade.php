<section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    @foreach ($summaryCards as $card)
        <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                <span class="h-2.5 w-2.5 rounded-full {{ $card['accent'] }}"></span>
            </div>
            <p class="mt-5 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $card['value'] }}</p>
            <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $card['caption'] }}</p>
        </article>
    @endforeach
</section>
