@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Notifications" />

    <div class="space-y-6">
        @if (session('status'))
            <x-ui.alert variant="success" :message="session('status')" />
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Notifications</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">Pusat alert kerja harian dari approval procurement, laboratorium, dan receivable.</p>
                </div>
                <div class="flex gap-3">
                    @if ($unreadCount > 0)
                        <form method="POST" action="{{ route('notifications.read-all') }}">
                            @csrf
                            <x-ui.button type="submit" variant="outline">Mark all read</x-ui.button>
                        </form>
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('notifications') }}" class="grid gap-4 md:grid-cols-[1.2fr_220px_auto]">
                <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari judul, pesan, atau module" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <select name="status" class="h-11 rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Semua status</option>
                    <option value="unread" @selected($filters['status'] === 'unread')>Unread</option>
                    <option value="read" @selected($filters['status'] === 'read')>Read</option>
                </select>
                <div class="flex gap-3">
                    <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
                    <a href="{{ route('notifications') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Reset</a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Notification list</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $notifications->firstItem() ?? 0 }} - {{ $notifications->lastItem() ?? 0 }} dari {{ $notifications->total() }} notifikasi.</p>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse ($notifications as $notification)
                    @php
                        $title = data_get($notification->data, 'title', 'System alert');
                        $message = data_get($notification->data, 'message', '');
                        $module = data_get($notification->data, 'module', 'system');
                        $level = data_get($notification->data, 'level', 'info');
                        $levelClasses = match ($level) {
                            'success' => 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300',
                            'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                            'danger' => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-300',
                            default => 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300',
                        };
                    @endphp
                    <div class="px-6 py-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</span>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $levelClasses }}">{{ ucfirst($level) }}</span>
                                    @if ($notification->read_at === null)
                                        <span class="inline-flex rounded-full bg-brand-100 px-2.5 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">Unread</span>
                                    @endif
                                </div>
                                <div class="text-xs uppercase tracking-[0.14em] text-gray-400">{{ str_replace('_', ' ', $module) }}</div>
                                <p class="text-sm leading-6 text-gray-500 dark:text-gray-400">{{ $message }}</p>
                                <div class="text-xs text-gray-400">{{ $notification->created_at?->format('d M Y H:i') }} | {{ $notification->created_at?->diffForHumans() }}</div>
                            </div>
                            <div class="flex gap-2">
                                @if ($notification->read_at === null)
                                    <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="outline">Mark read</x-ui.button>
                                    </form>
                                @endif
                                <a href="{{ route('notifications.open', $notification) }}" class="inline-flex h-11 items-center rounded-xl bg-brand-500 px-4 text-sm font-medium text-white transition hover:bg-brand-600">Open</a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada notifikasi.</div>
                @endforelse
            </div>
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $notifications->links() }}</div>
        </section>
    </div>
@endsection
