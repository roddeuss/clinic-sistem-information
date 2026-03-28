@props([
    'compact' => false,
])

<div class="flex items-center gap-3">
    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-brand-500 text-sm font-bold text-white shadow-theme-xs">
        CSI
    </div>

    @unless ($compact)
        <div>
            <p class="text-sm font-semibold text-gray-900 dark:text-white">CSI Clinic</p>
            <p class="text-xs uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">System Dashboard</p>
        </div>
    @endunless
</div>
