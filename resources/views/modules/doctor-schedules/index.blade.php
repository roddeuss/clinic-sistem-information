@php
    $defaultDoctorId = (string) ($doctorOptions->first()?->id ?? '');
    $defaultBranchId = (string) ($branchOptions->first()?->id ?? '');
    $defaultSectionId = (string) ($sectionOptions->first()?->id ?? '');

    $scheduleModalOpen = $errors->any() && in_array(old('form_context'), ['schedule-create', 'schedule-update'], true);
    $scheduleModalMode = old('form_context') === 'schedule-update' ? 'update' : 'create';
    $scheduleModalForm = [
        'id' => old('entity_id'),
        'doctor_id' => (string) old('doctor_id', $defaultDoctorId),
        'branch_id' => (string) old('branch_id', $defaultBranchId),
        'section_id' => (string) old('section_id', $defaultSectionId),
        'day_of_week' => (string) old('day_of_week', '1'),
        'start_time' => old('start_time', '08:00'),
        'end_time' => old('end_time', '12:00'),
        'slot_duration_minutes' => (int) old('slot_duration_minutes', 15),
        'max_patients' => (int) old('max_patients', 20),
        'room_label' => old('room_label', ''),
        'notes' => old('notes', ''),
        'is_active' => (int) old('is_active', 1) === 1,
    ];

    $leaveModalOpen = $errors->any() && in_array(old('form_context'), ['leave-create', 'leave-update'], true);
    $leaveModalMode = old('form_context') === 'leave-update' ? 'update' : 'create';
    $leaveModalForm = [
        'id' => old('entity_id'),
        'doctor_id' => (string) old('doctor_id', $defaultDoctorId),
        'branch_id' => (string) old('branch_id', $defaultBranchId),
        'leave_date' => old('leave_date', now()->format('Y-m-d')),
        'leave_type' => old('leave_type', 'full_day'),
        'start_time' => old('start_time', '08:00'),
        'end_time' => old('end_time', '12:00'),
        'notes' => old('notes', ''),
        'is_active' => (int) old('is_active', 1) === 1,
    ];

    $canCreateSchedule = $doctorOptions->isNotEmpty() && $branchOptions->isNotEmpty() && $sectionOptions->isNotEmpty();
    $canCreateLeave = $doctorOptions->isNotEmpty() && $branchOptions->isNotEmpty();
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Doctor Schedules" />

    <div
        x-data="{
            scheduleModalOpen: @js($scheduleModalOpen),
            leaveModalOpen: @js($leaveModalOpen),
            scheduleMode: @js($scheduleModalMode),
            leaveMode: @js($leaveModalMode),
            scheduleStoreAction: @js(route('doctor-schedules.store')),
            scheduleUpdateBase: @js(url('/doctor-schedules')),
            leaveStoreAction: @js(route('doctor-leaves.store')),
            leaveUpdateBase: @js(url('/doctor-schedules/leaves')),
            defaultDoctorId: @js($defaultDoctorId),
            defaultBranchId: @js($defaultBranchId),
            defaultSectionId: @js($defaultSectionId),
            scheduleForm: @js($scheduleModalForm),
            leaveForm: @js($leaveModalForm),
            openCreateSchedule() {
                this.scheduleMode = 'create';
                this.scheduleForm = {
                    id: null,
                    doctor_id: this.defaultDoctorId,
                    branch_id: this.defaultBranchId,
                    section_id: this.defaultSectionId,
                    day_of_week: '1',
                    start_time: '08:00',
                    end_time: '12:00',
                    slot_duration_minutes: 15,
                    max_patients: 20,
                    room_label: '',
                    notes: '',
                    is_active: true,
                };
                this.scheduleModalOpen = true;
            },
            openEditSchedule(schedule) {
                this.scheduleMode = 'update';
                this.scheduleForm = schedule;
                this.scheduleModalOpen = true;
            },
            openCreateLeave() {
                this.leaveMode = 'create';
                this.leaveForm = {
                    id: null,
                    doctor_id: this.defaultDoctorId,
                    branch_id: this.defaultBranchId,
                    leave_date: @js(now()->format('Y-m-d')),
                    leave_type: 'full_day',
                    start_time: '08:00',
                    end_time: '12:00',
                    notes: '',
                    is_active: true,
                };
                this.leaveModalOpen = true;
            },
            openEditLeave(leave) {
                this.leaveMode = 'update';
                this.leaveForm = leave;
                this.leaveModalOpen = true;
            },
            closeScheduleModal() {
                this.scheduleModalOpen = false;
            },
            closeLeaveModal() {
                this.leaveModalOpen = false;
            },
        }"
        @keydown.escape.window="closeScheduleModal(); closeLeaveModal()"
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
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Data table doctor schedules</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                        Kelola jadwal praktek mingguan per doctor per branch dan section, lengkap dengan slot pasien otomatis dan manajemen cuti doctor.
                    </p>
                    @if (! $canCreateSchedule)
                        <p class="mt-3 text-sm font-medium text-amber-600 dark:text-amber-300">
                            Pastikan doctor, branch, dan section sudah tersedia sebelum membuat schedule. Doctor leave tetap bisa disimpan jika data doctor dan branch sudah ada.
                        </p>
                    @endif
                </div>

                <div class="flex flex-wrap gap-3">
                    @if ($abilities['create'] && $canCreateSchedule)
                        <x-ui.button type="button" x-on:click="openCreateSchedule()">
                            Tambah Schedule
                        </x-ui.button>
                    @endif

                    @if ($abilities['create'] && $canCreateLeave)
                        <x-ui.button type="button" variant="outline" x-on:click="openCreateLeave()">
                            Tambah Leave
                        </x-ui.button>
                    @endif
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <form method="GET" action="{{ route('doctor-schedules') }}" class="grid gap-4 md:grid-cols-[1.3fr_220px_240px_240px_180px_auto]">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari doctor, branch, section, spesialisasi, atau catatan" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Branch</label>
                    <select name="branch" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua branch</option>
                        @foreach ($branchOptions as $branchOption)
                            <option value="{{ $branchOption->id }}" @selected($filters['branch'] === (string) $branchOption->id)>{{ $branchOption->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Doctor</label>
                    <select name="doctor" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua doctor</option>
                        @foreach ($doctorOptions as $doctorOption)
                            <option value="{{ $doctorOption->id }}" @selected($filters['doctor'] === (string) $doctorOption->id)>{{ $doctorOption->displayName() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Section</label>
                    <select name="section" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua section</option>
                        @foreach ($sectionOptions as $sectionOption)
                            <option value="{{ $sectionOption->id }}" @selected($filters['section'] === (string) $sectionOption->id)>
                                {{ $sectionOption->branch?->name }} - {{ $sectionOption->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <select name="status" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua status</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                    </select>
                </div>

                <div class="flex items-end gap-3">
                    <x-ui.button type="submit" variant="outline">Filter</x-ui.button>
                    <a href="{{ route('doctor-schedules') }}" class="inline-flex h-11 items-center rounded-xl border border-gray-200 px-4 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Weekly schedule list</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Menampilkan {{ $schedules->firstItem() ?? 0 }} - {{ $schedules->lastItem() ?? 0 }} dari {{ $schedules->total() }} schedule.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Doctor</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Branch</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Section</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Practice Time</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Slots</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($schedules as $schedule)
                            @php
                                $schedulePayload = [
                                    'id' => $schedule->id,
                                    'doctor_id' => (string) $schedule->doctor_id,
                                    'branch_id' => (string) $schedule->branch_id,
                                    'section_id' => (string) $schedule->section_id,
                                    'day_of_week' => (string) $schedule->day_of_week,
                                    'start_time' => substr($schedule->start_time, 0, 5),
                                    'end_time' => substr($schedule->end_time, 0, 5),
                                    'slot_duration_minutes' => $schedule->slot_duration_minutes,
                                    'max_patients' => $schedule->max_patients,
                                    'room_label' => $schedule->room_label ?? '',
                                    'notes' => $schedule->notes ?? '',
                                    'is_active' => $schedule->is_active,
                                ];
                            @endphp

                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $schedule->doctor?->displayName() ?? '-' }}</p>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $schedule->doctor?->specialization ?? '-' }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $schedule->branch?->name ?? '-' }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $schedule->branch?->code ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $schedule->section?->name ?? '-' }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $schedule->section?->code ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $dayOptions[$schedule->day_of_week] ?? '-' }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ substr($schedule->start_time, 0, 5) }} - {{ substr($schedule->end_time, 0, 5) }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $schedule->room_label ?: 'Room belum diisi' }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $schedule->slot_duration_minutes }} menit / slot</div>
                                    <div class="mt-1 text-xs text-gray-400">Max {{ $schedule->max_patients }} pasien</div>
                                    @if ($schedule->notes)
                                        <div class="mt-2 text-xs text-gray-400">{{ $schedule->notes }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $schedule->is_active ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300' }}">
                                        {{ $schedule->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'])
                                            <button
                                                type="button"
                                                title="Edit schedule"
                                                aria-label="Edit schedule"
                                                data-payload='@json($schedulePayload)' x-on:click='openEditSchedule(JSON.parse($el.dataset.payload))'
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white"
                                            >
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                                    <path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                            </button>
                                        @endif

                                        @if ($abilities['delete'])
                                            <form method="POST" action="{{ route('doctor-schedules.delete', $schedule) }}" onsubmit="return confirm('Hapus schedule doctor ini?')">
                                                @csrf
                                                <x-ui.icon-button type="submit" variant="danger" title="Hapus schedule">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                        <path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    </svg>
                                                </x-ui.icon-button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Belum ada data schedule doctor yang cocok dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                {{ $schedules->links() }}
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex flex-col gap-1">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Doctor leave list</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Menampilkan {{ $leaves->firstItem() ?? 0 }} - {{ $leaves->lastItem() ?? 0 }} dari {{ $leaves->total() }} leave.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Doctor</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Branch</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Date</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Type</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Notes</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Status</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($leaves as $leave)
                            @php
                                $leavePayload = [
                                    'id' => $leave->id,
                                    'doctor_id' => (string) $leave->doctor_id,
                                    'branch_id' => (string) $leave->branch_id,
                                    'leave_date' => $leave->leave_date?->format('Y-m-d') ?? '',
                                    'leave_type' => $leave->leave_type,
                                    'start_time' => $leave->start_time ? substr($leave->start_time, 0, 5) : '',
                                    'end_time' => $leave->end_time ? substr($leave->end_time, 0, 5) : '',
                                    'notes' => $leave->notes ?? '',
                                    'is_active' => $leave->is_active,
                                ];
                            @endphp

                            <tr class="align-top">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ $leave->doctor?->displayName() ?? '-' }}</p>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $leave->doctor?->specialization ?? '-' }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $leave->branch?->name ?? '-' }}</div>
                                    <div class="mt-1 text-xs text-gray-400">{{ $leave->branch?->code ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $leave->leave_date?->format('d M Y') ?? '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    <div>{{ $leaveTypeOptions[$leave->leave_type] ?? $leave->leave_type }}</div>
                                    <div class="mt-1 text-xs text-gray-400">
                                        {{ $leave->leave_type === 'full_day' ? 'Seharian penuh' : (substr($leave->start_time, 0, 5) . ' - ' . substr($leave->end_time, 0, 5)) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $leave->notes ?: '-' }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $leave->is_active ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-white/[0.04] dark:text-gray-300' }}">
                                        {{ $leave->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        @if ($abilities['edit'])
                                            <button
                                                type="button"
                                                title="Edit leave"
                                                aria-label="Edit leave"
                                                data-payload='@json($leavePayload)' x-on:click='openEditLeave(JSON.parse($el.dataset.payload))'
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/[0.03] dark:hover:text-white"
                                            >
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                    <path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                                                    <path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                            </button>
                                        @endif

                                        @if ($abilities['delete'])
                                            <form method="POST" action="{{ route('doctor-leaves.delete', $leave) }}" onsubmit="return confirm('Hapus leave doctor ini?')">
                                                @csrf
                                                <x-ui.icon-button type="submit" variant="danger" title="Hapus leave">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                                        <path d="M5 7H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M9.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M14.5 11V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M7 7L8 18.5C8.06066 19.3284 8.75104 19.9643 9.58166 19.9643H14.4183C15.249 19.9643 15.9393 19.3284 16 18.5L17 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                        <path d="M9 7V5.75C9 5.05964 9.55964 4.5 10.25 4.5H13.75C14.4404 4.5 15 5.05964 15 5.75V7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    </svg>
                                                </x-ui.icon-button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Belum ada data leave doctor yang cocok dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                {{ $leaves->links() }}
            </div>
        </section>

        <x-ui.modal show="scheduleModalOpen" maxWidth="5xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="scheduleMode === 'create' ? 'Tambah Doctor Schedule' : 'Update Doctor Schedule'"></h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Schedule hanya bisa dibuat jika doctor sudah terhubung ke section yang dipilih di Doctor Management.
                        </p>
                    </div>

                    <x-ui.icon-button title="Tutup modal" x-on:click="closeScheduleModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </x-ui.icon-button>
                </div>
            </div>

            <form method="POST" x-bind:action="scheduleMode === 'create' ? scheduleStoreAction : `${scheduleUpdateBase}/${scheduleForm.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="scheduleMode === 'create' ? 'schedule-create' : 'schedule-update'">
                <input type="hidden" name="entity_id" x-bind:value="scheduleForm.id">

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Doctor</label>
                        <select x-model="scheduleForm.doctor_id" name="doctor_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($doctorOptions as $doctorOption)
                                <option value="{{ $doctorOption->id }}">{{ $doctorOption->displayName() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Branch</label>
                        <select x-model="scheduleForm.branch_id" name="branch_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($branchOptions as $branchOption)
                                <option value="{{ $branchOption->id }}">{{ $branchOption->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Section</label>
                        <select x-model="scheduleForm.section_id" name="section_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($sectionOptions as $sectionOption)
                                <option value="{{ $sectionOption->id }}">{{ $sectionOption->branch?->name }} - {{ $sectionOption->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Day</label>
                        <select x-model="scheduleForm.day_of_week" name="day_of_week" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($dayOptions as $dayValue => $dayLabel)
                                <option value="{{ $dayValue }}">{{ $dayLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Start time</label>
                        <input x-model="scheduleForm.start_time" type="time" name="start_time" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">End time</label>
                        <input x-model="scheduleForm.end_time" type="time" name="end_time" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Slot duration</label>
                        <input x-model="scheduleForm.slot_duration_minutes" type="number" min="5" max="240" name="slot_duration_minutes" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Max patients</label>
                        <input x-model="scheduleForm.max_patients" type="number" min="1" max="500" name="max_patients" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Room label</label>
                        <input x-model="scheduleForm.room_label" type="text" name="room_label" placeholder="Mis. Room 2A" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                        <textarea x-model="scheduleForm.notes" name="notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                    </div>

                    <div class="flex items-end md:col-span-2">
                        <div>
                            <input type="hidden" name="is_active" x-bind:value="scheduleForm.is_active ? 1 : 0">
                            <label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input x-model="scheduleForm.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                                <span>Schedule aktif</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeScheduleModal()">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="scheduleMode === 'create' ? 'Simpan Schedule' : 'Update Schedule'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <x-ui.modal show="leaveModalOpen" maxWidth="4xl">
            <div class="border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="leaveMode === 'create' ? 'Tambah Doctor Leave' : 'Update Doctor Leave'"></h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Gunakan `Full Day` untuk cuti seharian penuh, atau `Partial Time` jika doctor hanya off pada jam tertentu.
                        </p>
                    </div>

                    <x-ui.icon-button title="Tutup modal" x-on:click="closeLeaveModal()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6L18 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <path d="M18 6L6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        </svg>
                    </x-ui.icon-button>
                </div>
            </div>

            <form method="POST" x-bind:action="leaveMode === 'create' ? leaveStoreAction : `${leaveUpdateBase}/${leaveForm.id}`" class="space-y-5 p-6">
                @csrf
                <input type="hidden" name="form_context" x-bind:value="leaveMode === 'create' ? 'leave-create' : 'leave-update'">
                <input type="hidden" name="entity_id" x-bind:value="leaveForm.id">

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Doctor</label>
                        <select x-model="leaveForm.doctor_id" name="doctor_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($doctorOptions as $doctorOption)
                                <option value="{{ $doctorOption->id }}">{{ $doctorOption->displayName() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Branch</label>
                        <select x-model="leaveForm.branch_id" name="branch_id" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($branchOptions as $branchOption)
                                <option value="{{ $branchOption->id }}">{{ $branchOption->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Leave date</label>
                        <input x-model="leaveForm.leave_date" type="date" name="leave_date" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Leave type</label>
                        <select x-model="leaveForm.leave_type" name="leave_type" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($leaveTypeOptions as $leaveTypeValue => $leaveTypeLabel)
                                <option value="{{ $leaveTypeValue }}">{{ $leaveTypeLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Start time</label>
                        <input x-model="leaveForm.start_time" type="time" name="start_time" x-bind:disabled="leaveForm.leave_type === 'full_day'" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:disabled:bg-gray-800">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">End time</label>
                        <input x-model="leaveForm.end_time" type="time" name="end_time" x-bind:disabled="leaveForm.leave_type === 'full_day'" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:disabled:bg-gray-800">
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                        <textarea x-model="leaveForm.notes" name="notes" rows="3" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white"></textarea>
                    </div>

                    <div class="flex items-end md:col-span-2">
                        <div>
                            <input type="hidden" name="is_active" x-bind:value="leaveForm.is_active ? 1 : 0">
                            <label class="inline-flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                                <input x-model="leaveForm.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                                <span>Leave aktif</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <x-ui.button type="button" variant="outline" x-on:click="closeLeaveModal()">Batal</x-ui.button>
                    <x-ui.button type="submit" x-text="leaveMode === 'create' ? 'Simpan Leave' : 'Update Leave'"></x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    </div>
@endsection

