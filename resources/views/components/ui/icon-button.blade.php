@props([
    'variant' => 'neutral',
    'title' => null,
    'className' => '',
])

@php
    $base = 'inline-flex h-9 w-9 items-center justify-center rounded-lg border transition focus:outline-none focus:ring-2 focus:ring-brand-500/20';

    $variants = [
        'neutral' => 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white',
        'primary' => 'border-brand-200 bg-brand-50 text-brand-600 hover:bg-brand-100 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-300',
        'danger' => 'border-red-200 bg-red-50 text-red-600 hover:bg-red-100 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300',
    ];

    $classes = trim($base . ' ' . ($variants[$variant] ?? $variants['neutral']) . ' ' . $className);
@endphp

<button
    {{ $attributes->merge([
        'type' => $attributes->get('type', 'button'),
        'class' => $classes,
        'title' => $title,
        'aria-label' => $title,
    ]) }}
>
    {{ $slot }}
</button>
