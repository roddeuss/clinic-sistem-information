@extends('layouts.app')

@section('content')
    @php
        $isLaboratory = ($order->laboratoryTest?->diagnostic_category ?? 'laboratory') === 'laboratory';
        $requestLabel = $isLaboratory ? 'Laboratory Request' : 'Diagnostic Request';
        $requestSheetLabel = $isLaboratory ? 'Laboratory Request Sheet' : 'Diagnostic Request Sheet';
    @endphp
    <div class="mx-auto max-w-4xl space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 print:shadow-none">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-5 dark:border-gray-800">
                <div>
                    <div class="text-sm font-semibold uppercase tracking-[0.18em] text-gray-400">{{ $requestLabel }}</div>
                    <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $order->visitRegistration?->branch?->clinic?->name ?? 'CSI Clinic HIS' }}</h1>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $order->visitRegistration?->branch?->name }} | {{ $order->visitRegistration?->branch?->code }}</div>
                </div>
                <div class="text-right text-sm text-gray-500 dark:text-gray-400">
                    <div class="font-medium text-gray-900 dark:text-white">{{ $order->visitRegistration?->branch?->clinic?->invoice_header ?? $requestSheetLabel }}</div>
                    <div class="mt-2">Order {{ $order->id }}</div>
                    <div>{{ $order->ordered_at?->format('d M Y H:i') }}</div>
                    <div class="mt-2">Printed {{ now()->format('d M Y H:i') }}</div>
                </div>
            </div>

            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="text-xs uppercase tracking-[0.14em] text-gray-400">Patient</div>
                    <div class="mt-2 font-semibold text-gray-900 dark:text-white">{{ $order->visitRegistration?->patient?->full_name }}</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $order->visitRegistration?->patientBranchRecord?->medical_record_no }} | {{ $order->visitRegistration?->patient?->phone }}</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Section {{ $order->visitRegistration?->section?->name ?? '-' }}</div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="text-xs uppercase tracking-[0.14em] text-gray-400">Request</div>
                    <div class="mt-2 font-semibold text-gray-900 dark:text-white">{{ $order->laboratoryTest?->name }}</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $order->laboratoryTest?->code }} | {{ ucfirst($order->provider_type) }} | {{ $order->laboratoryTest?->diagnostic_category }}</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Doctor {{ $order->orderedByDoctor?->displayName() ?? '-' }}</div>
                    @if ($order->provider_type === 'external')
                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Partner {{ $order->partner_name ?: '-' }}</div>
                    @endif
                </div>
            </div>

            <div class="mt-6 rounded-2xl border border-gray-200 dark:border-gray-800">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Requested test</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Dipakai untuk internal lab maupun partner lab eksternal.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-white/[0.02]">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Code</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Parameter / Test</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Unit</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Reference</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @forelse ($order->laboratoryTest?->parameters ?? collect() as $parameter)
                                <tr>
                                    <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $parameter->code ?: '-' }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-900 dark:text-white">{{ $parameter->name }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $parameter->unit ?: '-' }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $parameter->reference_range ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $order->laboratoryTest?->name }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <div class="rounded-2xl border border-gray-200 p-4 text-sm text-gray-600 dark:border-gray-800 dark:text-gray-300">
                    <div class="text-xs uppercase tracking-[0.14em] text-gray-400">Clinical note</div>
                    <div class="mt-2">{{ $order->notes ?: 'Tidak ada catatan tambahan.' }}</div>
                    @if ($order->external_reference_no)
                        <div class="mt-3 text-xs text-gray-400">External reference {{ $order->external_reference_no }}</div>
                    @endif
                </div>
                <div class="rounded-2xl border border-gray-200 p-4 text-sm text-gray-600 dark:border-gray-800 dark:text-gray-300">
                    <div class="text-xs uppercase tracking-[0.14em] text-gray-400">Status</div>
                    <div class="mt-2">Current status {{ ucfirst($order->status) }}</div>
                    <div class="mt-1">Provider {{ ucfirst($order->provider_type) }}</div>
                    <div class="mt-1">Requested by {{ auth()->user()?->name ?? 'System' }}</div>
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-3 print:hidden">
            <a href="{{ route('laboratory') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Back</a>
            <button type="button" onclick="window.print()" class="inline-flex h-11 items-center rounded-xl bg-brand-500 px-4 text-sm font-medium text-white transition hover:bg-brand-600">Print / Save PDF</button>
        </div>
    </div>
@endsection
