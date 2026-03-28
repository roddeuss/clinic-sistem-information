@extends('layouts.app')

@section('content')
    @php
        $title = match ($doctorLetter->letter_type) {
            'sick_note' => 'SURAT SAKIT',
            'fit_note' => 'SURAT KETERANGAN SEHAT',
            'drug_free_note' => 'SURAT KETERANGAN BEBAS NARKOBA',
            default => 'SURAT KONTROL',
        };
    @endphp

    <div class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Doctor Letter</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Print / Save PDF</p>
            </div>
            <button type="button" onclick="window.print()" class="inline-flex h-11 items-center rounded-xl bg-brand-600 px-4 text-sm font-medium text-white">Print / Save PDF</button>
        </div>

        <section class="rounded-2xl border border-gray-200 bg-white p-8 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 pb-6 text-center dark:border-gray-800">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $doctorLetter->branch?->clinic?->name ?? 'CSI Clinic' }}</h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $doctorLetter->branch?->address ?? '-' }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $doctorLetter->branch?->phone ?? '-' }}</p>
                <p class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">{{ $title }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $doctorLetter->letter_no }}</p>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Patient</p>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $doctorLetter->patient?->full_name }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">MR No: {{ $doctorLetter->patientBranchRecord?->medical_record_no ?? '-' }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Phone: {{ $doctorLetter->patient?->phone ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Issue Date</p>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $doctorLetter->issue_date?->translatedFormat('d F Y') ?? '-' }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $doctorLetter->doctor_name_snapshot ?: '-' }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $doctorLetter->doctor_specialization_snapshot ?: '-' }}</p>
                </div>
            </div>

            <div class="mt-8 space-y-5 text-sm leading-7 text-gray-700 dark:text-gray-300">
                @if ($doctorLetter->letter_type === 'sick_note')
                    <p>Dengan ini menerangkan bahwa pasien di atas telah diperiksa dan memerlukan istirahat/sakit mulai tanggal <span class="font-semibold">{{ $doctorLetter->sick_start_date?->translatedFormat('d F Y') }}</span> sampai dengan <span class="font-semibold">{{ $doctorLetter->sick_end_date?->translatedFormat('d F Y') }}</span> selama <span class="font-semibold">{{ $doctorLetter->sick_total_days }}</span> hari.</p>
                @elseif ($doctorLetter->letter_type === 'fit_note')
                    <p>{{ $doctorLetter->healthy_statement }}</p>
                @elseif ($doctorLetter->letter_type === 'drug_free_note')
                    <p>Dengan ini menerangkan bahwa pasien di atas telah menjalani pemeriksaan / screening pada tanggal <span class="font-semibold">{{ $doctorLetter->drug_test_date?->translatedFormat('d F Y') ?? '-' }}</span> dengan metode <span class="font-semibold">{{ $doctorLetter->drug_test_method ?? '-' }}</span> dan hasil <span class="font-semibold">{{ $doctorLetter->drug_test_result ?? '-' }}</span>.</p>
                    <p>{{ $doctorLetter->drug_free_statement }}</p>
                @else
                    <p>Pasien di atas dianjurkan untuk kontrol kembali pada tanggal <span class="font-semibold">{{ $doctorLetter->control_date?->translatedFormat('d F Y') }}</span>.</p>
                    @if ($doctorLetter->control_notes)
                        <p>{{ $doctorLetter->control_notes }}</p>
                    @endif
                @endif

                @if ($doctorLetter->diagnosis_summary)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Diagnosis Summary</p>
                        <p class="mt-2 whitespace-pre-line">{{ $doctorLetter->diagnosis_summary }}</p>
                    </div>
                @endif

                @if ($doctorLetter->notes)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Notes</p>
                        <p class="mt-2 whitespace-pre-line">{{ $doctorLetter->notes }}</p>
                    </div>
                @endif
            </div>

            <div class="mt-10 flex justify-end">
                <div class="w-full max-w-xs text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ now()->translatedFormat('d F Y') }}</p>
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Dokter Pemeriksa</p>
                    @if ($doctorLetter->doctor_signature_path_snapshot)
                        <img src="{{ asset('storage/' . $doctorLetter->doctor_signature_path_snapshot) }}" alt="Doctor signature" class="mx-auto mt-4 h-20 object-contain">
                    @else
                        <div class="mx-auto mt-4 h-20 w-40 rounded-lg border border-dashed border-gray-300"></div>
                    @endif
                    <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">{{ $doctorLetter->doctor_name_snapshot ?: '-' }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $doctorLetter->doctor_specialization_snapshot ?: '-' }}</p>
                    <p class="text-xs text-gray-400">SIP: {{ $doctorLetter->sip_number_snapshot ?: '-' }}</p>
                </div>
            </div>
        </section>
    </div>
@endsection
