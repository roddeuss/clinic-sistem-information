@php
    $visitModalOpen = $errors->any() && in_array(old('form_context'), ['visit-create', 'visit-update'], true);
    $visitModalMode = old('form_context') === 'visit-update' ? 'update' : 'create';
    $visitModalForm = [
        'id' => old('entity_id'),
        'patient_id' => (string) old('patient_id', ''),
        'section_id' => (string) old('section_id', ''),
        'visit_date' => old('visit_date', now()->toDateString()),
        'visit_type' => old('visit_type', 'same_day'),
        'doctor_schedule_id' => (string) old('doctor_schedule_id', ''),
        'slot_start_time' => old('slot_start_time', ''),
        'slot_end_time' => old('slot_end_time', ''),
        'notes' => old('notes', ''),
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Visit Registrations" />

    <div
        x-data="{
            modalOpen: @js($visitModalOpen),
            mode: @js($visitModalMode),
            storeAction: @js(route('visit-registrations.store')),
            updateBase: @js(url('/visit-registrations')),
            availabilityUrl: @js(route('visit-registrations.availability')),
            form: @js($visitModalForm),
            slotOptions: [],
            slotValue: '',
            async init() {
                if (this.form.section_id && this.form.visit_type !== 'emergency') {
                    await this.refreshSlots();
                }
                this.slotValue = this.currentSlotKey();
            },
            async openCreate() {
                this.mode = 'create';
                this.form = {
                    id: null,
                    patient_id: '',
                    section_id: '',
                    visit_date: @js(now()->toDateString()),
                    visit_type: 'same_day',
                    doctor_schedule_id: '',
                    slot_start_time: '',
                    slot_end_time: '',
                    notes: '',
                };
                this.slotOptions = [];
                this.slotValue = '';
                this.modalOpen = true;
            },
            async openEdit(payload) {
                this.mode = 'update';
                this.form = payload;
                this.modalOpen = true;
                if (this.form.visit_type !== 'emergency') {
                    await this.refreshSlots();
                } else {
                    this.slotOptions = [];
                }
                this.slotValue = this.currentSlotKey();
            },
            closeModal() { this.modalOpen = false; },
            currentSlotKey() {
                if (!this.form.doctor_schedule_id || !this.form.slot_start_time || !this.form.slot_end_time) return '';
                return `${this.form.doctor_schedule_id}|${this.form.slot_start_time}|${this.form.slot_end_time}`;
            },
            async onVisitConfigChange() {
                this.form.doctor_schedule_id = '';
                this.form.slot_start_time = '';
                this.form.slot_end_time = '';
                this.slotValue = '';
                await this.refreshSlots();
            },
            async refreshSlots() {
                if (this.form.visit_type === 'emergency' || !this.form.section_id || !this.form.visit_date) {
                    this.slotOptions = [];
                    return;
                }

                const url = `${this.availabilityUrl}?section_id=${encodeURIComponent(this.form.section_id)}&visit_date=${encodeURIComponent(this.form.visit_date)}&visit_type=${encodeURIComponent(this.form.visit_type)}${this.form.id ? `&registration_id=${this.form.id}` : ''}`;
                const response = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });

                if (!response.ok) {
                    this.slotOptions = [];
                    return;
                }

                const payload = await response.json();
                this.slotOptions = payload.data ?? [];
            },
            selectSlot() {
                const selected = this.slotOptions.find((slot) => `${slot.doctor_schedule_id}|${slot.slot_start_time}|${slot.slot_end_time}` === this.slotValue);
                if (!selected) return;
                this.form.doctor_schedule_id = selected.doctor_schedule_id;
                this.form.slot_start_time = selected.slot_start_time;
                this.form.slot_end_time = selected.slot_end_time;
            },
        }"
        x-init="init()"
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
            <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Visit registration desk</h1>
                    <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">Registrasi kunjungan same day, booking, dan emergency menggunakan counter aktif sebagai branch kerja.</p>
                </div>

                <form method="POST" action="{{ route('active-counter.update') }}" class="grid gap-3 sm:grid-cols-[280px_auto]">
                    @csrf
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Counter aktif</label>
                        <select name="counter_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Pilih counter</option>
                            @foreach ($counterOptions as $counterOption)
                                <option value="{{ $counterOption->id }}" @selected($activeCounter?->id === $counterOption->id)>{{ $counterOption->branch?->code }} - {{ $counterOption->code }} {{ $counterOption->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-3">
                        <x-ui.button type="submit" variant="outline">Set Counter</x-ui.button>
                        @if ($abilities['create'])
                            <x-ui.button type="button" x-on:click="openCreate()">Tambah Registrasi</x-ui.button>
                        @endif
                    </div>
                </form>
            </div>

            @if ($activeCounter)
                <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-500/20 dark:bg-green-500/10 dark:text-green-100">
                    Counter aktif: <span class="font-medium">{{ $activeCounter->code }} - {{ $activeCounter->name }}</span> | Branch {{ $activeCounter->branch?->code }} - {{ $activeCounter->branch?->name }}
                </div>
            @else
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
                    Pilih counter aktif terlebih dahulu sebelum membuat registrasi kunjungan.
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('visit-registrations') }}" class="grid gap-4 xl:grid-cols-[1.2fr_180px_180px_220px_220px_220px_180px_160px_auto]">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari pasien, RM, dokter, booking code" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal</label>
                    <input type="date" name="date" value="{{ $filters['date'] }}" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                    <select name="type" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua type</option>
                        <option value="same_day" @selected($filters['type'] === 'same_day')>Same Day</option>
                        <option value="booking" @selected($filters['type'] === 'booking')>Booking</option>
                        <option value="emergency" @selected($filters['type'] === 'emergency')>Emergency</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <select name="status" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua status</option>
                        @foreach (['booked','queued','called','in_service','completed','skipped','cancelled'] as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Section</label>
                    <select name="section" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua section</option>
                        @foreach ($sectionOptions as $sectionOption)
                            <option value="{{ $sectionOption->id }}" @selected($filters['section'] === (string) $sectionOption->id)>{{ $sectionOption->name }}</option>
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
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Direction</label>
                    <select name="sort_direction" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="desc" @selected($filters['sort_direction'] === 'desc')>Descending</option>
                        <option value="asc" @selected($filters['sort_direction'] === 'asc')>Ascending</option>
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
                    <a href="{{ route('visit-registrations') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">Reset</a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Registration list</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Menampilkan {{ $registrations->firstItem() ?? 0 }} - {{ $registrations->lastItem() ?? 0 }} dari {{ $registrations->total() }} registrasi.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Patient</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Visit</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Section / Doctor</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Queue</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($registrations as $registration)
                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ $registration->patient?->full_name }}</p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $registration->patientBranchRecord?->medical_record_no }}</p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $registration->patient?->phone }}</p>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $registration->visit_date?->format('d M Y') }}</div>
                                    <div class="mt-1">{{ strtoupper(str_replace('_', ' ', $registration->visit_type)) }}</div>
                                    <div class="mt-1">{{ $registration->booking_code ?: '-' }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $registration->section?->name }}</div>
                                    <div class="mt-1">{{ $registration->doctor?->displayName() ?? 'Doctor belum dipilih' }}</div>
                                    <div class="mt-1">{{ $registration->slot_start_time ? substr($registration->slot_start_time, 0, 5) . ' - ' . substr($registration->slot_end_time, 0, 5) : 'Tanpa slot' }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $registration->queueTicket?->queue_code ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium bg-gray-100 text-gray-700 dark:bg-white/[0.04] dark:text-gray-200">
                                        {{ strtoupper(str_replace('_', ' ', $registration->registration_status)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'] && (! $registration->queueTicket || $registration->queueTicket->status === 'cancelled'))
                                            @php
                                                $editPayload = [
                                                    'id' => $registration->id,
                                                    'patient_id' => (string) $registration->patient_id,
                                                    'section_id' => (string) $registration->section_id,
                                                    'visit_date' => $registration->visit_date?->format('Y-m-d'),
                                                    'visit_type' => $registration->visit_type,
                                                    'doctor_schedule_id' => (string) ($registration->doctor_schedule_id ?? ''),
                                                    'slot_start_time' => $registration->slot_start_time,
                                                    'slot_end_time' => $registration->slot_end_time,
                                                    'notes' => $registration->notes,
                                                ];
                                            @endphp
                                            <button type="button" data-payload='@json($editPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)' x-on:click='openEdit(JSON.parse($el.dataset.payload))' class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                                    <path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                            </button>
                                        @endif

                                        @if ($abilities['check_in'] && $registration->visit_type === 'booking' && ! $registration->queueTicket && $registration->visit_date?->isToday())
                                            <form method="POST" action="{{ route('visit-registrations.check-in', $registration) }}">
                                                @csrf
                                                <x-ui.button type="submit" size="sm">Check-in</x-ui.button>
                                            </form>
                                        @endif

                                        @if ($abilities['cancel'] && ! in_array($registration->registration_status, ['completed', 'cancelled'], true))
                                            <form method="POST" action="{{ route('visit-registrations.cancel', $registration) }}" onsubmit="return confirm('Batalkan registrasi ini?')">
                                                @csrf
                                                <x-ui.icon-button type="submit" variant="danger" title="Batalkan registrasi">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                        <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    </svg>
                                                </x-ui.icon-button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada data registrasi yang cocok dengan filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $registrations->links() }}</div>
        </section>

        <x-ui.modal show="modalOpen" maxWidth="4xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="mode === 'create' ? 'Tambah Visit Registration' : 'Update Visit Registration'"></h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Same day dan booking menggunakan slot dokter. Emergency bypass jadwal dan langsung masuk antrian.</p>
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
                <input type="hidden" name="form_context" x-bind:value="mode === 'create' ? 'visit-create' : 'visit-update'">
                <input type="hidden" name="entity_id" x-bind:value="form.id">
                <input type="hidden" name="doctor_schedule_id" x-bind:value="form.doctor_schedule_id">
                <input type="hidden" name="slot_start_time" x-bind:value="form.slot_start_time">
                <input type="hidden" name="slot_end_time" x-bind:value="form.slot_end_time">

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Patient</label>
                        <select x-model="form.patient_id" name="patient_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Pilih patient</option>
                            @foreach ($patientOptions as $patientOption)
                                @php $rm = $patientOption->branchRecords->first()?->medical_record_no; @endphp
                                <option value="{{ $patientOption->id }}">{{ $patientOption->full_name }} | {{ $patientOption->phone }} | {{ $rm ?: 'RM baru' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Section</label>
                        <select x-model="form.section_id" @change="onVisitConfigChange()" name="section_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="">Pilih section</option>
                            @foreach ($sectionOptions as $sectionOption)
                                <option value="{{ $sectionOption->id }}">{{ strtoupper($sectionOption->type) }} | {{ $sectionOption->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Visit date</label>
                        <input x-model="form.visit_date" @change="onVisitConfigChange()" type="date" name="visit_date" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Visit type</label>
                        <select x-model="form.visit_type" @change="onVisitConfigChange()" name="visit_type" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            <option value="same_day">Same Day</option>
                            <option value="booking">Booking</option>
                            <option value="emergency">Emergency</option>
                        </select>
                    </div>
                </div>

                <div x-show="form.visit_type !== 'emergency'" class="space-y-3">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Slot dokter tersedia</label>
                    <select x-model="slotValue" @change="selectSlot()" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Pilih slot dokter</option>
                        <template x-for="slot in slotOptions" :key="`${slot.doctor_schedule_id}|${slot.slot_start_time}|${slot.slot_end_time}`">
                            <option :value="`${slot.doctor_schedule_id}|${slot.slot_start_time}|${slot.slot_end_time}`" x-text="slot.label"></option>
                        </template>
                    </select>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Kalau slot tidak muncul, berarti jadwal belum tersedia atau dokter sedang penuh/cuti.</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Catatan</label>
                    <textarea x-model="form.notes" name="notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeModal()">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="mode === 'create' ? 'Simpan Registrasi' : 'Update Registrasi'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection

