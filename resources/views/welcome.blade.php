<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CSI Clinic Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-white">
    <div class="mx-auto flex min-h-screen max-w-6xl flex-col px-6 py-8">
        <header class="flex items-center justify-between gap-4">
            <x-common.app-brand />
            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Buka Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="inline-flex items-center rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600">
                        Login Staff
                    </a>
                @endauth
            </div>
        </header>

        <main class="flex flex-1 items-center py-12">
            <div class="grid gap-8 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
                <section>
                    <span class="inline-flex rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">
                        Sistem Informasi Klinik
                    </span>
                    <h1 class="mt-5 text-4xl font-semibold leading-tight text-gray-900 dark:text-white">
                        CSI Clinic Dashboard untuk operasional internal yang rapi dan terukur.
                    </h1>
                    <p class="mt-4 max-w-2xl text-base leading-7 text-gray-500 dark:text-gray-400">
                        CSI Clinic Dashboard adalah pusat kontrol internal untuk login staf, manajemen akses, setting klinik dan cabang, serta fondasi modul operasional klinik berikutnya seperti pasien, antrian, dan billing.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white transition hover:bg-brand-600">
                                Masuk ke dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white transition hover:bg-brand-600">
                                Masuk sebagai staff
                            </a>
                        @endauth
                    </div>
                </section>

                <section class="grid gap-4">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Apa yang sudah ada</h2>
                        <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            Login, profile, password reset, session management, role & permission, menu sidebar dinamis, serta setting klinik dan cabang.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Cara kerja dashboard</h2>
                        <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            User login memakai auth session Laravel. Hak akses role dikelola dengan permission per modul, dan menu otomatis menyesuaikan role user yang aktif.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Arah berikutnya</h2>
                        <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            Setelah fondasi akses stabil, modul pasien, antrian realtime, jadwal dokter, farmasi, dan billing bisa dibangun di atas struktur yang sama.
                        </p>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
