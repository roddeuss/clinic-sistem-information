@php
    $modalOpen = $errors->any() && in_array(old('form_context'), ['vital-sign-create', 'vital-sign-update'], true);
    $modalMode = old('form_context') === 'vital-sign-update' ? 'update' : 'create';
    $modalForm = [
        'id' => old('entity_id'),
        'visit_registration_id' => (string) old('visit_registration_id', ''),
        'systolic_bp' => old('systolic_bp', ''),
        'diastolic_bp' => old('diastolic_bp', ''),
        'temperature_celsius' => old('temperature_celsius', ''),
        'pulse_rate' => old('pulse_rate', ''),
        'respiratory_rate' => old('respiratory_rate', ''),
        'weight_kg' => old('weight_kg', ''),
        'height_cm' => old('height_cm', ''),
        'spo2_percent' => old('spo2_percent', ''),
        'notes' => old('notes', ''),
        'recorded_at' => old('recorded_at', now()->format('Y-m-d\TH:i')),
    ];

    $visitOptionPayload = $visitOptions
        ->map(function ($visit) {
            return [
                'id' => (string) $visit->id,
                'label' => sprintf(
                    '%s | %s | %s | %s',
                    $visit->visit_date?->format('d M Y'),
                    $visit->patient?->full_name,
                    $visit->patientBranchRecord?->medical_record_no ?? '-',
                    $visit->section?->name ?? '-',
                ),
                'care_stage' => $visit->care_stage,
                'vital_status' => $visit->vital_status,
            ];
        })
        ->values();
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Vital Signs" />

    <div
        x-data="{
            modalOpen: @js($modalOpen),
            mode: @js($modalMode),
            storeAction: @js(route('vital-signs.store')),
            updateBase: @js(url('/vital-signs')),
            visitOptions: @js($visitOptionPayload),
            form: @js($modalForm),
            openCreate() {
                this.mode = 'create';
                this.form = {
                    id: null,
                    visit_registration_id: '',
                    systolic_bp: '',
                    diastolic_bp: '',
                    temperature_celsius: '',
                    pulse_rate: '',
                    respiratory_rate: '',
                    weight_kg: '',
                    height_cm: '',
                    spo2_percent: '',
                    notes: '',
                    recorded_at: @js(now()->format('Y-m-d\TH:i')),
                };
                this.modalOpen = true;
            },
            openEdit(payload) {
                this.mode = 'update';
                this.form = payload;
                this.modalOpen = true;
            },
            closeModal() {
                this.modalOpen = false;
            },
            visitLabel(id) {
                const visit = this.visitOptions.find((item) => item.id === `${id}`);
                return visit ? visit.label : '';
            },
        }"
        @keydown.escape.window="closeModal()"
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Vital signs desk</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Input pengukuran perawat sebelum dokter memeriksa. Sistem menyimpan histori pengukuran per visit dan otomatis menghitung BMI dari berat badan dan tinggi badan.
                    </p>
                </div>

                @if ($abilities['create'])
                    <x-ui.button type="button" x-on:click="openCreate()">Tambah Vital</x-ui.button>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('vital-signs') }}" class="grid gap-4 xl:grid-cols-[1.2fr_180px_220px_220px_220px_160px_auto]">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari pasien, RM, atau section" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
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
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Sort</label>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <select name="sort_by" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                @foreach ($sortOptions as $sortKey => $sortLabel)
                                    <option value="{{ $sortKey }}" @selected($filters['sort_by'] === $sortKey)>{{ $sortLabel }}</option>
                                @endforeach
                            </select>
                            <select name="sort_direction" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                <option value="desc" @selected($filters['sort_direction'] === 'desc')>Desc</option>
                                <option value="asc" @selected($filters['sort_direction'] === 'asc')>Asc</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Per page</label>
                        <select name="per_page" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($perPageOptions as $option)
                                <option value="{{ $option }}" @selected($filters['per_page'] === $option)>{{ $option }} rows</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-3">
                        <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
                        <a href="{{ route('vital-signs') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">Reset</a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Vital signs list</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $vitalSigns->firstItem() ?? 0 }} - {{ $vitalSigns->lastItem() ?? 0 }} dari {{ $vitalSigns->total() }} pengukuran.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Visit</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Vitals</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Stage</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Recorder</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($vitalSigns as $vitalSign)
                            @php
                                $editPayload = [
                                    'id' => $vitalSign->id,
                                    'visit_registration_id' => (string) $vitalSign->visit_registration_id,
                                    'systolic_bp' => $vitalSign->systolic_bp,
                                    'diastolic_bp' => $vitalSign->diastolic_bp,
                                    'temperature_celsius' => $vitalSign->temperature_celsius,
                                    'pulse_rate' => $vitalSign->pulse_rate,
                                    'respiratory_rate' => $vitalSign->respiratory_rate,
                                    'weight_kg' => $vitalSign->weight_kg,
                                    'height_cm' => $vitalSign->height_cm,
                                    'spo2_percent' => $vitalSign->spo2_percent,
                                    'notes' => $vitalSign->notes,
                                    'recorded_at' => $vitalSign->recorded_at?->format('Y-m-d\TH:i'),
                                ];
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $vitalSign->patient?->full_name }}</p>
                                    <p class="mt-1">{{ $vitalSign->visitRegistration?->patientBranchRecord?->medical_record_no }}</p>
                                    <p class="mt-1">{{ $vitalSign->branch?->code }} - {{ $vitalSign->section?->name }}</p>
                                    <p class="mt-1">{{ $vitalSign->recorded_at?->format('d M Y H:i') }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    <div>TD: {{ $vitalSign->systolic_bp }}/{{ $vitalSign->diastolic_bp }} mmHg</div>
                                    <div class="mt-1">Suhu: {{ $vitalSign->temperature_celsius }}°C | Nadi: {{ $vitalSign->pulse_rate }}/min</div>
                                    <div class="mt-1">BB/TB: {{ $vitalSign->weight_kg }} kg / {{ $vitalSign->height_cm }} cm | BMI: {{ $vitalSign->bmi ?? '-' }}</div>
                                    <div class="mt-1">SpO2: {{ $vitalSign->spo2_percent }}% | RR: {{ $vitalSign->respiratory_rate ?: '-' }}</div>
                                    @if ($vitalSign->notes)
                                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $vitalSign->notes }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    <div>Vital: {{ strtoupper($vitalSign->visitRegistration?->vital_status ?? '-') }}</div>
                                    <div class="mt-1">Care: {{ strtoupper(str_replace('_', ' ', $vitalSign->visitRegistration?->care_stage ?? '-')) }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $vitalSign->recordedBy?->name ?? '-' }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'])
                                            <x-ui.icon-button type="button" title="Edit vital signs" data-payload='@json($editPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)' x-on:click='openEdit(JSON.parse($el.dataset.payload))'>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5V6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </x-ui.icon-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Belum ada vital signs yang cocok dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                {{ $vitalSigns->links() }}
            </div>
        </section>

        <x-ui.modal show="modalOpen" maxWidth="4xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="mode === 'create' ? 'Tambah Vital Signs' : 'Update Vital Signs'"></h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Satu visit boleh punya lebih dari satu pengukuran. Gunakan update jika hanya ingin mengoreksi input terakhir.</p>
                    </div>

                    <x-ui.icon-button title="Tutup modal" x-on:click="closeModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </x-ui.icon-button>
                </div>
            </div>

            <form method="POST" x-bind:action="mode === 'create' ? storeAction : `${updateBase}/${form.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="mode === 'create' ? 'vital-sign-create' : 'vital-sign-update'">
                <input type="hidden" name="entity_id" x-bind:value="form.id">

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Visit</label>
                    <select x-model="form.visit_registration_id" name="visit_registration_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Pilih visit</option>
                        <template x-for="visit in visitOptions" :key="visit.id">
                            <option :value="visit.id" x-text="visit.label"></option>
                        </template>
                    </select>
                </div>

                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Systolic</label>
                        <input x-model="form.systolic_bp" type="number" name="systolic_bp" min="50" max="300" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Diastolic</label>
                        <input x-model="form.diastolic_bp" type="number" name="diastolic_bp" min="30" max="200" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Temperature (°C)</label>
                        <input x-model="form.temperature_celsius" type="number" step="0.1" name="temperature_celsius" min="30" max="45" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Pulse</label>
                        <input x-model="form.pulse_rate" type="number" name="pulse_rate" min="20" max="250" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Respiratory rate</label>
                        <input x-model="form.respiratory_rate" type="number" name="respiratory_rate" min="5" max="80" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Weight (kg)</label>
                        <input x-model="form.weight_kg" type="number" step="0.01" name="weight_kg" min="1" max="300" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Height (cm)</label>
                        <input x-model="form.height_cm" type="number" step="0.01" name="height_cm" min="30" max="250" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">SpO2 (%)</label>
                        <input x-model="form.spo2_percent" type="number" name="spo2_percent" min="50" max="100" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-[1fr_240px]">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                        <textarea x-model="form.notes" name="notes" rows="3" class="w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Recorded at</label>
                        <input x-model="form.recorded_at" type="datetime-local" name="recorded_at" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeModal()">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="mode === 'create' ? 'Simpan Vital' : 'Update Vital'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection

