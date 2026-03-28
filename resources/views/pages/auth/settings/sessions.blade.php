@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Sessions">
        <x-slot:breadcrumbs>
            <li>
                <a href="{{ route('dashboard') }}" class="text-gray-700 hover:text-brand-600 dark:text-gray-400 dark:hover:text-brand-500">Dashboard</a>
            </li>
            <li>
                <span class="text-gray-700 dark:text-gray-400">Sessions</span>
            </li>
        </x-slot:breadcrumbs>
    </x-common.page-breadcrumb>

    <x-layouts.settings title="Active sessions" description="Review recent logins and revoke any device you no longer trust">
        @if (session('status'))
            <div class="mb-6">
                <x-ui.alert variant="success" :message="session('status')" />
            </div>
        @endif

        @error('session')
            <div class="mb-6">
                <x-ui.alert variant="error" :message="$message" />
            </div>
        @enderror

        <div class="space-y-4">
            @forelse ($sessions as $session)
                <div class="rounded-lg border border-gray-200 px-5 py-4 dark:border-gray-700">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $session['user_agent'] }}
                                </h3>

                                @if ($session['is_current'])
                                    <span class="inline-flex rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700 dark:bg-green-500/15 dark:text-green-400">
                                        Current session
                                    </span>
                                @endif
                            </div>

                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                IP: {{ $session['ip_address'] }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Last active: {{ $session['last_active_at']->diffForHumans() }}
                            </p>
                        </div>

                        @if (! $session['is_current'])
                            <form method="POST" action="{{ route('settings.sessions.destroy', $session['id']) }}">
                                @csrf
                                @method('DELETE')

                                <x-ui.button
                                    type="submit"
                                    variant="primary"
                                    className="bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-800"
                                >
                                    Revoke session
                                </x-ui.button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 px-5 py-8 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-400">
                    No active sessions were found for this account.
                </div>
            @endforelse
        </div>
    </x-layouts.settings>
@endsection
