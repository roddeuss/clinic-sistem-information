@props([
    'show' => 'false',
    'maxWidth' => '2xl',
])

@php
    $widths = [
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        '4xl' => 'max-w-4xl',
    ];

    $widthClass = $widths[$maxWidth] ?? $widths['2xl'];
@endphp

<div x-show="{{ $show }}" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-6">
    <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-[1px]" @click="{{ $show }} = false"></div>

    <div class="relative flex min-h-full items-center justify-center">
        <div class="relative w-full {{ $widthClass }} rounded-2xl border border-gray-200 bg-white shadow-theme-xl dark:border-gray-800 dark:bg-gray-900">
            {{ $slot }}
        </div>
    </div>
</div>
