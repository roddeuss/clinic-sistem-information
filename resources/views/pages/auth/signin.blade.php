@extends('layouts.fullscreen-layout')

@section('content')
    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-10 dark:bg-gray-950">
        <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-8 text-center">
                <div class="flex justify-center">
                    <x-common.app-brand />
                </div>
                <h1 class="mt-6 text-2xl font-semibold text-gray-900 dark:text-white">Masuk ke dashboard</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Gunakan akun internal untuk mengakses operasional CSI Clinic.
                </p>
            </div>

            <form method="POST" action="{{ route('login') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Email
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        autofocus
                        class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white @error('email') border-error-500 @enderror"
                    />
                    @error('email')
                        <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="mb-1.5 flex items-center justify-between gap-3">
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Password
                        </label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-600 transition hover:text-brand-500">
                                Lupa password?
                            </a>
                        @endif
                    </div>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-950 dark:text-white @error('password') border-error-500 @enderror"
                    />
                    @error('password')
                        <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>
                    @enderror
                </div>

                <label for="remember" class="inline-flex items-center gap-3 text-sm text-gray-600 dark:text-gray-400">
                    <input type="checkbox" id="remember" name="remember" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                    <span>Ingat perangkat ini</span>
                </label>

                <button
                    type="submit"
                    class="flex h-11 w-full items-center justify-center rounded-xl bg-brand-500 px-4 text-sm font-medium text-white transition hover:bg-brand-600"
                >
                    Login
                </button>
            </form>

            <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 px-4 py-4 text-sm text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400">
                Akun dibuat oleh administrator. Jika akses belum aktif, hubungi admin klinik.
            </div>
        </div>
    </div>
@endsection
