@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="User Profile" />

    <div class="rounded-[28px] border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="max-w-2xl">
            <div class="mb-4 inline-flex rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
                Placeholder Page
            </div>
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">
                Profile detail screen belum kita pakai.
            </h2>
            <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                Untuk fase sekarang, pengelolaan profil staf difokuskan lewat halaman settings. Halaman ini sengaja disederhanakan dulu supaya struktur Blade tetap bersih dan tidak memanggil komponen template yang tidak tersedia.
            </p>
            <a
                href="{{ route('settings.profile.edit') }}"
                class="mt-6 inline-flex items-center rounded-2xl bg-teal-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-teal-500"
            >
                Open Profile Settings
            </a>
        </div>
    </div>
@endsection
