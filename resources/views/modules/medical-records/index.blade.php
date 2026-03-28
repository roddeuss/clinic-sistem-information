@php
    $recordModalOpen = $errors->any() && in_array(old('form_context'), ['medical-record-create', 'medical-record-update'], true);
    $recordModalMode = old('form_context') === 'medical-record-update' ? 'update' : 'create';
    $recordModalForm = [
        'id' => old('entity_id'),
        'visit_registration_id' => (string) old('visit_registration_id', ''),
        'doctor_id' => (string) old('doctor_id', ''),
        'subjective' => old('subjective', ''),
        'objective' => old('objective', ''),
        'assessment' => old('assessment', ''),
        'plan' => old('plan', ''),
        'diagnosis_notes' => old('diagnosis_notes', ''),
        'primary_icd10_id' => (string) old('primary_icd10_id', ''),
        'secondary_icd10_ids' => collect(old('secondary_icd10_ids', []))->map(fn ($id) => (string) $id)->values()->all(),
        'submit_action' => old('submit_action', 'draft'),
    ];

    $reopenModalOpen = $errors->any() && old('form_context') === 'medical-record-reopen';
    $reopenForm = [
        'id' => old('entity_id'),
        'reason' => old('reason', ''),
    ];

    $visitOptionPayload = $visitOptions
        ->map(function ($visit) {
            $latestVital = $visit->latestVitalSign;

            return [
                'id' => (string) $visit->id,
                'label' => sprintf(
                    '%s | %s | %s | %s',
                    $visit->visit_date?->format('d M Y'),
                    $visit->patient?->full_name,
                    $visit->patientBranchRecord?->medical_record_no ?? '-',
                    $visit->section?->name ?? '-',
                ),
                'doctor_options' => $visit->section?->doctors
                    ? $visit->section->doctors
                        ->where('is_active', true)
                        ->map(fn ($doctor) => [
                            'id' => (string) $doctor->id,
                            'label' => $doctor->displayName(),
                        ])
                        ->values()
                        ->all()
                    : [],
                'latest_vital_summary' => $latestVital
                    ? sprintf(
                        'TD %s/%s | Suhu %s°C | Nadi %s | SpO2 %s%%',
                        $latestVital->systolic_bp,
                        $latestVital->diastolic_bp,
                        $latestVital->temperature_celsius,
                        $latestVital->pulse_rate,
                        $latestVital->spo2_percent,
                    )
                    : 'Belum ada vital signs',
            ];
        })
        ->values();

    $icd10OptionPayload = $icd10Options
        ->map(fn ($code) => [
            'id' => (string) $code->id,
            'label' => sprintf('%s - %s', $code->code, $code->name_en),
            'label_id' => $code->name_id,
        ])
        ->values();

    $canRequestReopen = auth()->user()?->hasRole('doctor') || $abilities['direct_reopen'];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Medical Records" />

    <div
        x-data="{
            recordModalOpen: @js($recordModalOpen),
            recordMode: @js($recordModalMode),
            reopenModalOpen: @js($reopenModalOpen),
            reopenMode: 'request',
            storeAction: @js(route('medical-records.store')),
            updateBase: @js(url('/medical-records')),
            requestReopenBase: @js(url('/medical-records')),
            visitOptions: @js($visitOptionPayload),
            icd10Options: @js($icd10OptionPayload),
            recordForm: @js($recordModalForm),
            reopenForm: @js($reopenForm),
            openCreate(payload = {}) {
                this.recordMode = 'create';
                this.recordForm = {
                    id: null,
                    visit_registration_id: payload.visit_registration_id ?? '',
                    doctor_id: payload.doctor_id ?? '',
                    subjective: '',
                    objective: '',
                    assessment: '',
                    plan: '',
                    diagnosis_notes: '',
                    primary_icd10_id: '',
                    secondary_icd10_ids: [],
                    submit_action: 'draft',
                };
                this.syncDoctorSelection();
                this.recordModalOpen = true;
            },
            openEdit(payload) {
                this.recordMode = 'update';
                this.recordForm = payload;
                this.syncDoctorSelection();
                this.recordModalOpen = true;
            },
            openReopen(recordId, mode) {
                this.reopenMode = mode;
                this.reopenForm = {
                    id: recordId,
                    reason: '',
                };
                this.reopenModalOpen = true;
            },
            closeRecordModal() {
                this.recordModalOpen = false;
            },
            closeReopenModal() {
                this.reopenModalOpen = false;
            },
            currentVisit() {
                return this.visitOptions.find((item) => item.id === `${this.recordForm.visit_registration_id}`) ?? null;
            },
            doctorOptions() {
                const visit = this.currentVisit();
                return visit ? visit.doctor_options : [];
            },
            vitalSummary() {
                const visit = this.currentVisit();
                return visit ? visit.latest_vital_summary : 'Pilih visit terlebih dahulu.';
            },
            syncDoctorSelection() {
                const options = this.doctorOptions();
                if (!options.length) {
                    this.recordForm.doctor_id = '';
                    return;
                }

                const exists = options.some((item) => item.id === `${this.recordForm.doctor_id}`);
                if (!exists) {
                    this.recordForm.doctor_id = options[0].id;
                }
            },
            reopenAction() {
                return this.reopenMode === 'approve'
                    ? `${this.requestReopenBase}/${this.reopenForm.id}/approve-reopen`
                    : `${this.requestReopenBase}/${this.reopenForm.id}/request-reopen`;
            },
        }"
        @keydown.escape.window="closeRecordModal(); closeReopenModal();"
        class="space-y-6"
    >
        @if (session('status'))
            <x-ui.alert variant="success" :message="session('status')" />
        @endif

        @if ($errors->any())
            <x-ui.alert variant="error" :message="$errors->first()" />
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">SOAP / Medical records</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Dokter mengisi SOAP per visit, memilih diagnosis primary dan secondary dari ICD-10, lalu finalisasi. Setelah final, perubahan dilakukan lewat request re-open atau approval admin.
                    </p>
                </div>

                @if ($abilities['create'])
                    <x-ui.button type="button" x-on:click="openCreate()">Tambah SOAP</x-ui.button>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('medical-records') }}" class="grid gap-4 xl:grid-cols-[1.15fr_180px_220px_220px_180px_180px_140px_auto]">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari pasien, RM, atau dokter" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal</label>
                    <input type="date" name="date" value="{{ $filters['date'] }}" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Branch</label>
                    <select name="branch" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua branch</option>
                        @foreach ($branchOptions as $branchOption)
                            <option value="{{ $branchOption->id }}" @selected($filters['branch'] === (string) $branchOption->id)>{{ $branchOption->code }} - {{ $branchOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Section</label>
                    <select name="section" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua section</option>
                        @foreach ($sectionOptions as $sectionOption)
                            <option value="{{ $sectionOption->id }}" @selected($filters['section'] === (string) $sectionOption->id)>{{ $sectionOption->branch?->code }} - {{ $sectionOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <select name="status" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua status</option>
                        @foreach (['pending', 'draft', 'final', 'reopen_requested', 'reopened'] as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ strtoupper(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Sort by</label>
                    <select name="sort_by" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        @foreach ($sortOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['sort_by'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Per page</label>
                    <select name="per_page" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        @foreach ($perPageOptions as $perPageOption)
                            <option value="{{ $perPageOption }}" @selected($filters['per_page'] === $perPageOption)>{{ $perPageOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-3">
                    <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
                    <a href="{{ route('medical-records') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">Reset</a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Visit SOAP list</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $visits->firstItem() ?? 0 }} - {{ $visits->lastItem() ?? 0 }} dari {{ $visits->total() }} visit.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Patient</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Visit</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Vitals</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Record</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($visits as $visit)
                            @php
                                $record = $visit->medicalRecord;
                                $primaryDiagnosis = $record?->diagnoses->firstWhere('diagnosis_type', 'primary');
                                $secondaryDiagnoses = $record?->diagnoses->where('diagnosis_type', 'secondary') ?? collect();
                                $doctorOptions = $visit->section?->doctors
                                    ? $visit->section->doctors
                                        ->where('is_active', true)
                                        ->map(fn ($doctor) => [
                                            'id' => (string) $doctor->id,
                                            'label' => $doctor->displayName(),
                                        ])
                                        ->values()
                                        ->all()
                                    : [];
                                $createPayload = [
                                    'visit_registration_id' => (string) $visit->id,
                                    'doctor_id' => (string) ($visit->doctor_id ?: ($doctorOptions[0]['id'] ?? '')),
                                ];
                                $editPayload = $record
                                    ? [
                                        'id' => $record->id,
                                        'visit_registration_id' => (string) $visit->id,
                                        'doctor_id' => (string) ($record->doctor_id ?: ($visit->doctor_id ?: ($doctorOptions[0]['id'] ?? ''))),
                                        'subjective' => $record->subjective,
                                        'objective' => $record->objective,
                                        'assessment' => $record->assessment,
                                        'plan' => $record->plan,
                                        'diagnosis_notes' => $record->diagnosis_notes,
                                        'primary_icd10_id' => (string) optional($primaryDiagnosis)->icd10_code_id,
                                        'secondary_icd10_ids' => $secondaryDiagnoses->pluck('icd10_code_id')->map(fn ($id) => (string) $id)->values()->all(),
                                        'submit_action' => 'draft',
                                    ]
                                    : null;
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $visit->patient?->full_name }}</p>
                                    <p class="mt-1">{{ $visit->patientBranchRecord?->medical_record_no }}</p>
                                    <p class="mt-1">{{ $visit->patient?->phone }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    <div>{{ $visit->visit_date?->format('d M Y') }}</div>
                                    <div class="mt-1">{{ $visit->branch?->code }} - {{ $visit->section?->name }}</div>
                                    <div class="mt-1">Care: {{ strtoupper(str_replace('_', ' ', $visit->care_stage)) }}</div>
                                    <div class="mt-1">Doctor: {{ $record?->doctor?->displayName() ?? $visit->doctor?->displayName() ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    @if ($visit->latestVitalSign)
                                        <div>TD {{ $visit->latestVitalSign->systolic_bp }}/{{ $visit->latestVitalSign->diastolic_bp }}</div>
                                        <div class="mt-1">Suhu {{ $visit->latestVitalSign->temperature_celsius }}°C | Nadi {{ $visit->latestVitalSign->pulse_rate }}</div>
                                        <div class="mt-1">SpO2 {{ $visit->latestVitalSign->spo2_percent }}%</div>
                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $visit->latestVitalSign->recorded_at?->format('d M Y H:i') }}</div>
                                    @else
                                        <span class="text-xs text-amber-700 dark:text-amber-300">Belum ada vital signs</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    @if ($record)
                                        <div class="font-medium text-gray-900 dark:text-white">{{ strtoupper(str_replace('_', ' ', $record->status)) }}</div>
                                        @if ($primaryDiagnosis?->icd10Code)
                                            <div class="mt-1">Primary: {{ $primaryDiagnosis->icd10Code->code }}</div>
                                        @endif
                                        @if ($secondaryDiagnoses->isNotEmpty())
                                            <div class="mt-1">Secondary: {{ $secondaryDiagnoses->pluck('icd10Code.code')->filter()->implode(', ') }}</div>
                                        @endif
                                        @if ($record->finalized_at)
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Final {{ $record->finalized_at->format('d M Y H:i') }}</div>
                                        @endif
                                    @else
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Belum ada SOAP</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if (! $record && $abilities['create'])
                                            <x-ui.icon-button type="button" title="Buat SOAP" data-payload='@json($createPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)' x-on:click='openCreate(JSON.parse($el.dataset.payload))'>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M12 5V19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    <path d="M5 12H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                            </x-ui.icon-button>
                                        @endif

                                        @if ($record && in_array($record->status, ['draft', 'reopened'], true) && $abilities['edit'])
                                            <x-ui.icon-button type="button" title="Edit SOAP" data-payload='@json($editPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)' x-on:click='openEdit(JSON.parse($el.dataset.payload))'>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5V6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </x-ui.icon-button>
                                        @endif

                                        @if ($record && $record->status === 'final' && $canRequestReopen)
                                            <x-ui.icon-button type="button" title="{{ $abilities['direct_reopen'] ? 'Re-open SOAP' : 'Request Re-open' }}" x-on:click="openReopen('{{ $record->id }}', '{{ $abilities['direct_reopen'] ? 'direct' : 'request' }}')">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M20 4V10H14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    <path d="M4 20V14H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    <path d="M5.5 9.5C6.40934 6.96783 8.82986 5.25 11.5672 5.25C15.1365 5.25 18.0672 8.18066 18.0672 11.75C18.0672 15.3193 15.1365 18.25 11.5672 18.25C8.82986 18.25 6.40934 16.5322 5.5 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                            </x-ui.icon-button>
                                        @endif

                                        @if ($record && $record->status === 'reopen_requested' && $abilities['approve_reopen'])
                                            <x-ui.icon-button type="button" title="Approve Re-open" x-on:click="openReopen('{{ $record->id }}', 'approve')">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M5 13L9 17L19 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </x-ui.icon-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Belum ada visit yang cocok dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                {{ $visits->links() }}
            </div>
        </section>

        <x-ui.modal show="recordModalOpen" maxWidth="6xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="recordMode === 'create' ? 'Tambah SOAP' : 'Update SOAP'"></h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pilih visit aktif, tentukan dokter dalam section yang sama, lalu simpan sebagai draft atau finalkan catatan klinis.</p>
                    </div>

                    <x-ui.icon-button title="Tutup modal" x-on:click="closeRecordModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </x-ui.icon-button>
                </div>
            </div>

            <form method="POST" x-bind:action="recordMode === 'create' ? storeAction : `${updateBase}/${recordForm.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="recordMode === 'create' ? 'medical-record-create' : 'medical-record-update'">
                <input type="hidden" name="entity_id" x-bind:value="recordForm.id">
                <input type="hidden" name="submit_action" x-bind:value="recordForm.submit_action">

                <div class="grid gap-5 md:grid-cols-[1.4fr_1fr]">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Visit</label>
                        <select x-model="recordForm.visit_registration_id" x-on:change="syncDoctorSelection()" name="visit_registration_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Pilih visit</option>
                            <template x-for="visit in visitOptions" :key="visit.id">
                                <option :value="visit.id" x-text="visit.label"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Doctor</label>
                        <select x-model="recordForm.doctor_id" name="doctor_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Pilih dokter</option>
                            <template x-for="doctor in doctorOptions()" :key="doctor.id">
                                <option :value="doctor.id" x-text="doctor.label"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-4 text-sm text-gray-600 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-300">
                    <span class="font-medium text-gray-900 dark:text-white">Ringkasan vital:</span>
                    <span x-text="vitalSummary()" class="ml-2"></span>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Subjective</label>
                        <textarea x-model="recordForm.subjective" name="subjective" rows="5" class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Objective</label>
                        <textarea x-model="recordForm.objective" name="objective" rows="5" class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Assessment</label>
                        <textarea x-model="recordForm.assessment" name="assessment" rows="5" class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Plan</label>
                        <textarea x-model="recordForm.plan" name="plan" rows="5" class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                    </div>
                </div>

                <div class="grid gap-5 xl:grid-cols-[1fr_1.4fr]">
                    <div class="space-y-5">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Primary diagnosis</label>
                            <select x-model="recordForm.primary_icd10_id" name="primary_icd10_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                <option value="">Pilih primary diagnosis</option>
                                <template x-for="code in icd10Options" :key="code.id">
                                    <option :value="code.id" x-text="code.label"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Diagnosis notes</label>
                            <textarea x-model="recordForm.diagnosis_notes" name="diagnosis_notes" rows="4" class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Secondary diagnoses</label>
                        <div class="max-h-80 overflow-y-auto rounded-2xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="grid gap-3 md:grid-cols-2">
                                @foreach ($icd10Options as $icd10Option)
                                    <label class="flex items-start gap-3 rounded-xl border border-gray-200 px-3 py-3 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-300">
                                        <input x-model="recordForm.secondary_icd10_ids" type="checkbox" name="secondary_icd10_ids[]" value="{{ $icd10Option->id }}" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                                        <span>{{ $icd10Option->code }} - {{ $icd10Option->name_en }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeRecordModal()">Batal</x-ui.button>
                    <x-ui.button type="submit" variant="outline" x-on:click="recordForm.submit_action = 'draft'">Simpan Draft</x-ui.button>
                    <x-ui.button type="submit" x-on:click="recordForm.submit_action = 'final'">Finalkan SOAP</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="reopenModalOpen" maxWidth="xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="reopenMode === 'approve' ? 'Approve Re-open SOAP' : 'Re-open SOAP'"></h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Alasan wajib disimpan untuk menjaga jejak perubahan rekam medis.</p>
                    </div>

                    <x-ui.icon-button title="Tutup modal" x-on:click="closeReopenModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </x-ui.icon-button>
                </div>
            </div>

            <form method="POST" x-bind:action="reopenAction()" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" value="medical-record-reopen">
                <input type="hidden" name="entity_id" x-bind:value="reopenForm.id">

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Reason</label>
                    <textarea x-model="reopenForm.reason" name="reason" rows="4" class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeReopenModal()">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="reopenMode === 'approve' ? 'Approve Re-open' : 'Proses Re-open'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection

