@extends('layouts.app')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Referral Letter</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Print / Save PDF</p>
            </div>
            <button type="button" onclick="window.print()" class="inline-flex h-11 items-center rounded-xl bg-brand-600 px-4 text-sm font-medium text-white">Print / Save PDF</button>
        </div>

        <section class="rounded-2xl border border-gray-200 bg-white p-8 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 pb-6 text-center dark:border-gray-800">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $referral->branch?->clinic?->name ?? 'CSI Clinic' }}</h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $referral->branch?->address ?? '-' }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $referral->branch?->phone ?? '-' }}</p>
                <p class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">SURAT RUJUKAN</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $referral->referral_no }}</p>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Patient</p>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $referral->patient?->full_name }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">MR No: {{ $referral->patientBranchRecord?->medical_record_no ?? '-' }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Phone: {{ $referral->patient?->phone ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Destination</p>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $referral->destination_name }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $referral->destination_address ?: '-' }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $referral->destination_phone ?: '-' }}</p>
                </div>
            </div>

            <div class="mt-6 space-y-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Diagnosis Summary</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $referral->diagnosis_summary ?: '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Clinical Summary</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $referral->clinical_summary ?: '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Treatment Summary</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $referral->treatment_summary ?: '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Reason</p>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $referral->reason ?: '-' }}</p>
                </div>
                @if ($referral->notes)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Notes</p>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $referral->notes }}</p>
                    </div>
                @endif
            </div>

            <div class="mt-10 flex justify-end">
                <div class="w-full max-w-xs text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ now()->translatedFormat('d F Y') }}</p>
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Dokter Pengirim</p>
                    @if ($referral->doctor_signature_path_snapshot)
                        <img src="{{ asset('storage/' . $referral->doctor_signature_path_snapshot) }}" alt="Doctor signature" class="mx-auto mt-4 h-20 object-contain">
                    @else
                        <div class="mx-auto mt-4 h-20 w-40 rounded-lg border border-dashed border-gray-300"></div>
                    @endif
                    <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">{{ $referral->doctor_name_snapshot ?: '-' }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $referral->doctor_specialization_snapshot ?: '-' }}</p>
                </div>
            </div>
        </section>
    </div>
@endsection
