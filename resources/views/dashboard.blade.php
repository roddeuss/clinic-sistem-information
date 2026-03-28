@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Dashboard" />

    <div class="space-y-6">
        @if (session('status'))
            <x-ui.alert variant="success" :message="session('status')" />
        @endif

        @include('dashboard.partials.hero')
        @include('dashboard.partials.summary-cards')
        @include('dashboard.partials.quick-actions')

        <section class="grid gap-6 xl:grid-cols-[1.28fr_0.72fr]">
            <div class="space-y-6">
                @include('dashboard.partials.main-panel', ['panel' => $mainPanel])

                @if (! empty($queuePanel))
                    @include('dashboard.partials.queue-panel', ['panel' => $queuePanel])
                @endif
            </div>

            <div class="space-y-6">
                @include('dashboard.partials.attention-panel', ['panel' => $attentionPanel])
                @include('dashboard.partials.notifications-panel', ['panel' => $notificationsPanel])
            </div>
        </section>
    </div>
@endsection
