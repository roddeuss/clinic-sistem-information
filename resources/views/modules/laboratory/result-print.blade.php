@extends('layouts.app')

@section('content')
    @php
        $isLaboratory = ($order->laboratoryTest?->diagnostic_category ?? 'laboratory') === 'laboratory';
        $resultLabel = $isLaboratory ? 'Laboratory Result' : 'Diagnostic Result';
    @endphp
    <div class="mx-auto max-w-4xl space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 print:shadow-none">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-5 dark:border-gray-800">
                <div>
                    <div class="text-sm font-semibold uppercase tracking-[0.18em] text-gray-400">{{ $resultLabel }}</div>
                    <h1 class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $order->visitRegistration?->branch?->clinic?->name ?? 'CSI Clinic HIS' }}</h1>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $order->visitRegistration?->branch?->name }} | {{ $order->visitRegistration?->branch?->code }}</div>
                </div>
                <div class="text-right text-sm text-gray-500 dark:text-gray-400">
                    <div class="font-medium text-gray-900 dark:text-white">Reviewed result sheet</div>
                    <div class="mt-2">Order {{ $order->id }}</div>
                    <div>Reviewed {{ $order->reviewed_at?->format('d M Y H:i') ?? '-' }}</div>
                    <div class="mt-2">Printed {{ now()->format('d M Y H:i') }}</div>
                </div>
            </div>

            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="text-xs uppercase tracking-[0.14em] text-gray-400">Patient</div>
                    <div class="mt-2 font-semibold text-gray-900 dark:text-white">{{ $order->visitRegistration?->patient?->full_name }}</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $order->visitRegistration?->patientBranchRecord?->medical_record_no }} | {{ $order->visitRegistration?->patient?->phone }}</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Doctor {{ $order->orderedByDoctor?->displayName() ?? '-' }}</div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="text-xs uppercase tracking-[0.14em] text-gray-400">Result meta</div>
                    <div class="mt-2 font-semibold text-gray-900 dark:text-white">{{ $order->laboratoryTest?->name }}</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ ucfirst($order->provider_type) }} | {{ $order->partner_name ?: 'Internal lab' }} | {{ $order->laboratoryTest?->diagnostic_category }}</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Resulted by {{ $order->resultedBy?->name ?? '-' }}</div>
                    <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Reviewed by {{ $order->reviewedBy?->name ?? '-' }}</div>
                </div>
            </div>

            <div class="mt-6 rounded-2xl border border-gray-200 dark:border-gray-800">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Structured result</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Hasil hanya bisa dicetak setelah status reviewed.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-white/[0.02]">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Parameter</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Value</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Unit</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Reference</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.14em] text-gray-500">Flag</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @forelse ($order->results as $result)
                                @php
                                    $flag = strtolower((string) ($result->result_flag ?? ''));
                                    $flagClasses = match ($flag) {
                                        'high', 'critical' => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-300',
                                        'low' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                                        'normal' => 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300',
                                        default => 'bg-gray-100 text-gray-600 dark:bg-white/[0.03] dark:text-gray-300',
                                    };
                                @endphp
                                <tr>
                                    <td class="px-5 py-3 text-sm text-gray-900 dark:text-white">{{ $result->parameter_name }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $result->value ?: '-' }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $result->unit ?: '-' }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $result->reference_range ?: '-' }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $flagClasses }}">{{ $result->result_flag ?: '-' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400">Belum ada hasil terstruktur. Dokumen ini tetap valid sebagai cover result karena status order sudah reviewed.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <div class="rounded-2xl border border-gray-200 p-4 text-sm text-gray-600 dark:border-gray-800 dark:text-gray-300">
                    <div class="text-xs uppercase tracking-[0.14em] text-gray-400">Attachment / external reference</div>
                    <div class="mt-2">{{ $order->result_attachment_path ?: 'Tidak ada attachment path.' }}</div>
                    @if ($order->external_reference_no)
                        <div class="mt-2 text-xs text-gray-400">External reference {{ $order->external_reference_no }}</div>
                    @endif
                </div>
                <div class="rounded-2xl border border-gray-200 p-4 text-sm text-gray-600 dark:border-gray-800 dark:text-gray-300">
                    <div class="text-xs uppercase tracking-[0.14em] text-gray-400">Clinical notes</div>
                    <div class="mt-2">{{ $order->notes ?: 'Tidak ada catatan tambahan.' }}</div>
                    <div class="mt-2 text-xs text-gray-400">Sample collected {{ $order->sample_collected_at?->format('d M Y H:i') ?? '-' }} | Resulted {{ $order->resulted_at?->format('d M Y H:i') ?? '-' }}</div>
                </div>
            </div>
            @if ($order->result_summary || $order->result_impression)
                <div class="mt-6 grid gap-5 md:grid-cols-2">
                    <div class="rounded-2xl border border-gray-200 p-4 text-sm text-gray-600 dark:border-gray-800 dark:text-gray-300">
                        <div class="text-xs uppercase tracking-[0.14em] text-gray-400">Result Summary</div>
                        <div class="mt-2 whitespace-pre-line">{{ $order->result_summary ?: 'Tidak ada summary.' }}</div>
                    </div>
                    <div class="rounded-2xl border border-gray-200 p-4 text-sm text-gray-600 dark:border-gray-800 dark:text-gray-300">
                        <div class="text-xs uppercase tracking-[0.14em] text-gray-400">Impression</div>
                        <div class="mt-2 whitespace-pre-line">{{ $order->result_impression ?: 'Tidak ada impression.' }}</div>
                    </div>
                </div>
            @endif
        </section>

        <div class="flex justify-end gap-3 print:hidden">
            <a href="{{ route('laboratory') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 dark:border-gray-700 dark:text-gray-300">Back</a>
            <button type="button" onclick="window.print()" class="inline-flex h-11 items-center rounded-xl bg-brand-500 px-4 text-sm font-medium text-white transition hover:bg-brand-600">Print / Save PDF</button>
        </div>
    </div>
@endsection
