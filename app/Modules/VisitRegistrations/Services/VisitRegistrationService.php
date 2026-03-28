<?php

namespace App\Modules\VisitRegistrations\Services;

use App\Models\DoctorLeave;
use App\Models\DoctorSchedule;
use App\Models\Patient;
use App\Models\Section;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Modules\VisitRegistrations\Exceptions\VisitRegistrationException;
use App\Services\ActiveCounterService;
use App\Services\AuditLogService;
use App\Services\PatientRecordService;
use App\Services\QueueService;
use App\Services\VisitDoctorAssignmentService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitRegistrationService
{
    private const ALLOWED_SORTS = [
        'visit_date',
        'created_at',
        'registration_status',
        'visit_type',
        'checked_in_at',
    ];

    public function __construct(
        private readonly ActiveCounterService $activeCounterService,
        private readonly PatientRecordService $patientRecordService,
        private readonly QueueService $queueService,
        private readonly VisitDoctorAssignmentService $visitDoctorAssignmentService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        $activeCounter = $this->activeCounterService->activeCounter();

        return [
            'filters' => $filters,
            'activeCounter' => $activeCounter,
            'counterOptions' => $this->activeCounterService->options(),
            'registrations' => $this->registrationTable($filters, $activeCounter?->branch_id),
            'sectionOptions' => $this->sectionOptions($activeCounter?->branch_id),
            'patientOptions' => $this->patientOptions($activeCounter?->branch_id),
            'abilities' => $this->abilities(),
            'sortOptions' => $this->sortOptions(),
            'perPageOptions' => [10, 25, 50, 100],
        ];
    }

    public function availability(array $filters): array
    {
        $activeCounter = $this->activeCounterService->requireActiveCounter();
        $section = $this->resolveSection((int) $filters['section_id'], $activeCounter->branch_id);
        $visitDate = Carbon::parse($filters['visit_date']);

        if (($filters['visit_type'] ?? 'same_day') === 'emergency') {
            return ['data' => []];
        }

        return [
            'data' => $this->slotOptions(
                $section,
                $visitDate,
                isset($filters['registration_id']) ? (int) $filters['registration_id'] : null,
            ),
        ];
    }

    public function createRegistration(array $payload, User $actor): VisitRegistration
    {
        return DB::transaction(function () use ($payload, $actor): VisitRegistration {
            $activeCounter = $this->activeCounterService->requireActiveCounter();
            $section = $this->resolveSection((int) $payload['section_id'], $activeCounter->branch_id);
            $visitDate = Carbon::parse($payload['visit_date']);
            $patient = $this->resolvePatient((int) $payload['patient_id']);
            $patientBranchRecord = $this->patientRecordService->ensureBranchRecord($patient, $activeCounter->branch);
            $slot = $this->resolveSlotPayload($section, $visitDate, $payload);

            $registration = VisitRegistration::query()->create(
                $this->buildRegistrationAttributes(
                    payload: $payload,
                    patient: $patient,
                    patientBranchRecordId: $patientBranchRecord->id,
                    branchId: $activeCounter->branch_id,
                    counterId: $activeCounter->id,
                    sectionId: $section->id,
                    slot: $slot,
                )
            );

            $this->syncDoctorAssignment($registration, 'Initial assignment from visit registration.');
            $this->ensureQueueForImmediateVisit($registration);

            $registration->load($this->indexRelations());

            $this->auditLogService->log(
                module: 'visit_registration_management',
                action: 'create',
                auditable: $registration,
                description: sprintf('Registrasi kunjungan %s dibuat untuk patient %s oleh %s.', $registration->id, $patient->full_name, $actor->email),
                after: $this->auditSnapshot($registration),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'counter_id' => $activeCounter->getKey(),
                    'branch_id' => $activeCounter->branch_id,
                ],
            );

            return $registration;
        });
    }

    public function updateRegistration(VisitRegistration $registration, array $payload, User $actor): bool
    {
        return DB::transaction(function () use ($registration, $payload, $actor): bool {
            $lockedRegistration = VisitRegistration::query()
                ->with($this->indexRelations())
                ->lockForUpdate()
                ->findOrFail($registration->getKey());

            $this->guardEditableRegistration($lockedRegistration);

            $activeCounter = $this->activeCounterService->requireActiveCounter();
            $this->guardSameBranchContext($lockedRegistration, $activeCounter->branch_id);

            $section = $this->resolveSection((int) $payload['section_id'], $activeCounter->branch_id);
            $visitDate = Carbon::parse($payload['visit_date']);
            $patient = $this->resolvePatient((int) $payload['patient_id']);
            $patientBranchRecord = $this->patientRecordService->ensureBranchRecord($patient, $activeCounter->branch);
            $slot = $this->resolveSlotPayload($section, $visitDate, $payload, $lockedRegistration->getKey());
            $before = $this->auditSnapshot($lockedRegistration);

            $attributes = $this->buildRegistrationAttributes(
                payload: $payload,
                patient: $patient,
                patientBranchRecordId: $patientBranchRecord->id,
                branchId: $activeCounter->branch_id,
                counterId: $activeCounter->id,
                sectionId: $section->id,
                slot: $slot,
                existingRegistration: $lockedRegistration,
            );

            if (! $this->registrationChanged($lockedRegistration, $attributes)) {
                return false;
            }

            $lockedRegistration->fill($attributes);
            $lockedRegistration->save();

            $this->syncDoctorAssignment($lockedRegistration->fresh(['doctorAssignments', 'queueTicket', 'doctor', 'doctorSchedule']), 'Updated from visit registration.');
            $this->ensureQueueForImmediateVisit($lockedRegistration->fresh());

            $lockedRegistration->load($this->indexRelations());

            $this->auditLogService->log(
                module: 'visit_registration_management',
                action: 'update',
                auditable: $lockedRegistration,
                description: sprintf('Registrasi kunjungan %s diperbarui oleh %s.', $lockedRegistration->id, $actor->email),
                before: $before,
                after: $this->auditSnapshot($lockedRegistration),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'counter_id' => $activeCounter->getKey(),
                    'branch_id' => $activeCounter->branch_id,
                ],
            );

            return true;
        });
    }

    public function checkInBooking(VisitRegistration $registration, User $actor): bool
    {
        return DB::transaction(function () use ($registration, $actor): bool {
            $lockedRegistration = VisitRegistration::query()
                ->with($this->indexRelations())
                ->lockForUpdate()
                ->findOrFail($registration->getKey());

            $activeCounter = $this->activeCounterService->requireActiveCounter();
            $this->guardSameBranchContext($lockedRegistration, $activeCounter->branch_id);

            if ($lockedRegistration->visit_type !== 'booking') {
                throw new VisitRegistrationException('Hanya booking yang bisa check-in pada hari kunjungan.');
            }

            if ($lockedRegistration->registration_status === 'cancelled') {
                throw new VisitRegistrationException('Registrasi yang sudah dibatalkan tidak bisa check-in.');
            }

            if (! $lockedRegistration->visit_date->isToday()) {
                throw new VisitRegistrationException('Check-in hanya tersedia di hari kunjungan.');
            }

            $activeQueue = $lockedRegistration->queueTicket()
                ->where('status', '!=', 'cancelled')
                ->lockForUpdate()
                ->first();

            if ($activeQueue) {
                return false;
            }

            $before = $this->auditSnapshot($lockedRegistration);

            $lockedRegistration->forceFill([
                'counter_id' => $activeCounter->getKey(),
                'checked_in_at' => $lockedRegistration->checked_in_at ?? now(),
                'care_stage' => 'waiting_nurse',
                'registration_status' => 'queued',
                'cancelled_at' => null,
            ])->save();

            $this->queueService->createForRegistration($lockedRegistration->fresh());
            $lockedRegistration->load($this->indexRelations());

            $this->auditLogService->log(
                module: 'visit_registration_management',
                action: 'check_in',
                auditable: $lockedRegistration,
                description: sprintf('Booking registrasi %s berhasil check-in oleh %s.', $lockedRegistration->id, $actor->email),
                before: $before,
                after: $this->auditSnapshot($lockedRegistration),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'counter_id' => $activeCounter->getKey(),
                    'branch_id' => $activeCounter->branch_id,
                ],
            );

            return true;
        });
    }

    public function cancelRegistration(VisitRegistration $registration, User $actor, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($registration, $actor, $reason): bool {
            $lockedRegistration = VisitRegistration::query()
                ->with($this->indexRelations())
                ->lockForUpdate()
                ->findOrFail($registration->getKey());

            $activeCounter = $this->activeCounterService->requireActiveCounter();
            $this->guardSameBranchContext($lockedRegistration, $activeCounter->branch_id);

            if ($lockedRegistration->registration_status === 'completed') {
                throw new VisitRegistrationException('Registrasi yang sudah selesai tidak bisa dibatalkan.');
            }

            $before = $this->auditSnapshot($lockedRegistration);
            $activeQueue = $lockedRegistration->queueTicket()
                ->where('status', '!=', 'cancelled')
                ->lockForUpdate()
                ->first();

            if ($activeQueue) {
                $this->queueService->transition($activeQueue, 'cancel');
                $lockedRegistration->refresh()->load($this->indexRelations());
            } elseif ($lockedRegistration->registration_status === 'cancelled') {
                return false;
            } else {
                $lockedRegistration->forceFill([
                    'registration_status' => 'cancelled',
                    'care_stage' => 'cancelled',
                    'cancelled_at' => now(),
                ])->save();
                $lockedRegistration->load($this->indexRelations());
            }

            $this->auditLogService->log(
                module: 'visit_registration_management',
                action: 'cancel',
                auditable: $lockedRegistration,
                description: sprintf('Registrasi kunjungan %s dibatalkan oleh %s.', $lockedRegistration->id, $actor->email),
                before: $before,
                after: $this->auditSnapshot($lockedRegistration),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'counter_id' => $activeCounter->getKey(),
                    'branch_id' => $activeCounter->branch_id,
                    'reason' => $reason,
                ],
            );

            return true;
        });
    }

    public function registrationPayload(VisitRegistration $registration): array
    {
        $registration->loadMissing($this->indexRelations());

        return [
            'id' => $registration->getKey(),
            'patient_id' => $registration->patient_id,
            'patient_name' => $registration->patient?->full_name,
            'patient_phone' => $registration->patient?->phone,
            'medical_record_no' => $registration->patientBranchRecord?->medical_record_no,
            'branch_id' => $registration->branch_id,
            'counter_id' => $registration->counter_id,
            'counter_name' => $registration->counter?->name,
            'section_id' => $registration->section_id,
            'section_name' => $registration->section?->name,
            'doctor_id' => $registration->doctor_id,
            'doctor_name' => $registration->doctor?->displayName(),
            'doctor_schedule_id' => $registration->doctor_schedule_id,
            'visit_date' => $registration->visit_date?->toDateString(),
            'visit_type' => $registration->visit_type,
            'registration_status' => $registration->registration_status,
            'care_stage' => $registration->care_stage,
            'vital_status' => $registration->vital_status,
            'booking_code' => $registration->booking_code,
            'slot_start_time' => $registration->slot_start_time,
            'slot_end_time' => $registration->slot_end_time,
            'notes' => $registration->notes,
            'checked_in_at' => $registration->checked_in_at?->toIso8601String(),
            'queued_at' => $registration->queued_at?->toIso8601String(),
            'cancelled_at' => $registration->cancelled_at?->toIso8601String(),
            'queue' => $registration->queueTicket
                ? [
                    'id' => $registration->queueTicket->getKey(),
                    'queue_code' => $registration->queueTicket->queue_code,
                    'status' => $registration->queueTicket->status,
                ]
                : null,
        ];
    }

    private function registrationTable(array $filters, ?int $branchId): LengthAwarePaginator
    {
        return VisitRegistration::query()
            ->with($this->indexRelations())
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->searchForManagement($filters['search'])
            ->filterDate($filters['date'])
            ->filterStatus($filters['status'])
            ->filterType($filters['type'])
            ->filterSection($filters['section'])
            ->orderByAllowed($filters['sort_by'], $filters['sort_direction'], self::ALLOWED_SORTS)
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    private function sectionOptions(?int $branchId): Collection
    {
        return Section::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name', 'code', 'type', 'allow_appointment', 'allow_walk_in']);
    }

    private function patientOptions(?int $branchId): Collection
    {
        return Patient::query()
            ->with([
                'branchRecords' => fn ($query) => $query
                    ->when($branchId, fn ($recordQuery) => $recordQuery->where('branch_id', $branchId))
                    ->with('branch:id,name,code')
                    ->orderBy('medical_record_no'),
            ])
            ->where('is_active', true)
            ->orderBy('full_name')
            ->limit(150)
            ->get(['id', 'full_name', 'phone', 'date_of_birth']);
    }

    private function resolvePatient(int $patientId): Patient
    {
        $patient = Patient::query()
            ->where('is_active', true)
            ->find($patientId);

        if (! $patient) {
            throw ValidationException::withMessages([
                'patient_id' => 'Patient aktif tidak ditemukan.',
            ]);
        }

        return $patient;
    }

    private function resolveSection(int $sectionId, int $branchId): Section
    {
        return Section::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->findOrFail($sectionId);
    }

    private function resolveSlotPayload(Section $section, Carbon $visitDate, array $payload, ?int $ignoreRegistrationId = null): array
    {
        $visitType = $payload['visit_type'];

        if ($visitType === 'emergency') {
            if ($section->type !== 'emergency') {
                throw new VisitRegistrationException('Registrasi emergency harus diarahkan ke section emergency.');
            }

            if (! $visitDate->isToday()) {
                throw new VisitRegistrationException('Registrasi emergency hanya bisa dibuat untuk hari ini.');
            }

            return [
                'doctor_id' => null,
                'doctor_schedule_id' => null,
                'slot_start_time' => null,
                'slot_end_time' => null,
            ];
        }

        if ($section->type === 'emergency' || ! $section->allow_appointment) {
            throw new VisitRegistrationException('Section ini tidak menerima appointment terjadwal.');
        }

        if ($visitType === 'same_day' && ! $visitDate->isToday()) {
            throw new VisitRegistrationException('Same day visit harus menggunakan tanggal hari ini.');
        }

        if ($visitType === 'booking' && ! $visitDate->isFuture()) {
            throw new VisitRegistrationException('Booking harus menggunakan tanggal di masa depan.');
        }

        if (! filled($payload['doctor_schedule_id']) || ! filled($payload['slot_start_time']) || ! filled($payload['slot_end_time'])) {
            throw new VisitRegistrationException('Pilih slot dokter yang masih tersedia sebelum menyimpan registrasi.');
        }

        return $this->lockAndValidateSelectedSlot($section, $visitDate, $payload, $ignoreRegistrationId);
    }

    private function slotOptions(Section $section, Carbon $visitDate, ?int $ignoreRegistrationId = null): array
    {
        $schedules = DoctorSchedule::query()
            ->with('doctor:id,full_name,title_prefix,title_suffix,is_active')
            ->where('branch_id', $section->branch_id)
            ->where('section_id', $section->id)
            ->where('day_of_week', $visitDate->dayOfWeekIso)
            ->where('is_active', true)
            ->whereHas('doctor', fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('start_time')
            ->get();

        if ($schedules->isEmpty()) {
            return [];
        }

        $leavesByDoctor = DoctorLeave::query()
            ->whereIn('doctor_id', $schedules->pluck('doctor_id'))
            ->where('branch_id', $section->branch_id)
            ->whereDate('leave_date', $visitDate->toDateString())
            ->where('is_active', true)
            ->get()
            ->groupBy('doctor_id');

        $bookedSlots = VisitRegistration::query()
            ->when($ignoreRegistrationId, fn ($query) => $query->whereKeyNot($ignoreRegistrationId))
            ->whereDate('visit_date', $visitDate->toDateString())
            ->whereIn('doctor_schedule_id', $schedules->pluck('id'))
            ->whereNotIn('registration_status', ['cancelled'])
            ->get()
            ->mapWithKeys(fn (VisitRegistration $registration) => [
                $registration->doctor_schedule_id . '|' . $registration->slot_start_time . '|' . $registration->slot_end_time => true,
            ]);

        $options = [];

        foreach ($schedules as $schedule) {
            $cursor = Carbon::parse($visitDate->toDateString() . ' ' . $schedule->start_time);
            $scheduleEnd = Carbon::parse($visitDate->toDateString() . ' ' . $schedule->end_time);
            $generated = 0;

            while ($cursor->copy()->addMinutes($schedule->slot_duration_minutes)->lte($scheduleEnd) && $generated < $schedule->max_patients) {
                $slotStart = $cursor->copy();
                $slotEnd = $cursor->copy()->addMinutes($schedule->slot_duration_minutes);
                $generated++;
                $cursor->addMinutes($schedule->slot_duration_minutes);

                if ($this->overlapsDoctorLeave($slotStart, $slotEnd, $leavesByDoctor->get($schedule->doctor_id, collect()))) {
                    continue;
                }

                $slotKey = $schedule->id . '|' . $slotStart->format('H:i:s') . '|' . $slotEnd->format('H:i:s');
                if ($bookedSlots->has($slotKey)) {
                    continue;
                }

                $doctorName = $schedule->doctor?->displayName() ?? $schedule->doctor?->full_name ?? 'Doctor';

                $options[] = [
                    'doctor_id' => $schedule->doctor_id,
                    'doctor_schedule_id' => $schedule->id,
                    'doctor_name' => $doctorName,
                    'slot_start_time' => $slotStart->format('H:i:s'),
                    'slot_end_time' => $slotEnd->format('H:i:s'),
                    'label' => sprintf(
                        '%s | %s - %s',
                        $doctorName,
                        $slotStart->format('H:i'),
                        $slotEnd->format('H:i'),
                    ),
                ];
            }
        }

        return collect($options)
            ->sortBy(['slot_start_time', 'doctor_name'])
            ->values()
            ->all();
    }

    private function lockAndValidateSelectedSlot(Section $section, Carbon $visitDate, array $payload, ?int $ignoreRegistrationId = null): array
    {
        $schedule = DoctorSchedule::query()
            ->with('doctor:id,full_name,title_prefix,title_suffix,is_active')
            ->whereKey($payload['doctor_schedule_id'])
            ->where('branch_id', $section->branch_id)
            ->where('section_id', $section->id)
            ->where('day_of_week', $visitDate->dayOfWeekIso)
            ->where('is_active', true)
            ->whereHas('doctor', fn (Builder $query) => $query->where('is_active', true))
            ->lockForUpdate()
            ->first();

        if (! $schedule) {
            throw new VisitRegistrationException('Jadwal dokter yang dipilih sudah tidak aktif atau tidak sesuai dengan section.');
        }

        $slotStart = Carbon::parse($visitDate->toDateString() . ' ' . $payload['slot_start_time']);
        $slotEnd = Carbon::parse($visitDate->toDateString() . ' ' . $payload['slot_end_time']);
        $scheduleStart = Carbon::parse($visitDate->toDateString() . ' ' . $schedule->start_time);
        $scheduleEnd = Carbon::parse($visitDate->toDateString() . ' ' . $schedule->end_time);

        if ($slotStart->lt($scheduleStart) || $slotEnd->gt($scheduleEnd)) {
            throw new VisitRegistrationException('Slot dokter berada di luar rentang jadwal praktik yang aktif.');
        }

        if ((int) $slotStart->diffInMinutes($slotEnd) !== (int) $schedule->slot_duration_minutes) {
            throw new VisitRegistrationException('Durasi slot dokter tidak sesuai dengan konfigurasi jadwal.');
        }

        $minutesFromStart = $scheduleStart->diffInMinutes($slotStart, false);

        if ($minutesFromStart < 0 || $minutesFromStart % $schedule->slot_duration_minutes !== 0) {
            throw new VisitRegistrationException('Slot dokter tidak berada pada interval jadwal yang valid.');
        }

        $slotOrder = intdiv($minutesFromStart, $schedule->slot_duration_minutes) + 1;

        if ($slotOrder > $schedule->max_patients) {
            throw new VisitRegistrationException('Slot dokter berada di luar kapasitas maksimum jadwal.');
        }

        $leaves = DoctorLeave::query()
            ->where('doctor_id', $schedule->doctor_id)
            ->where('branch_id', $section->branch_id)
            ->whereDate('leave_date', $visitDate->toDateString())
            ->where('is_active', true)
            ->lockForUpdate()
            ->get();

        if ($this->overlapsDoctorLeave($slotStart, $slotEnd, $leaves)) {
            throw new VisitRegistrationException('Dokter sedang cuti atau tidak tersedia pada slot yang dipilih.');
        }

        $conflict = VisitRegistration::query()
            ->when($ignoreRegistrationId, fn ($query) => $query->whereKeyNot($ignoreRegistrationId))
            ->whereDate('visit_date', $visitDate->toDateString())
            ->where('doctor_schedule_id', $schedule->getKey())
            ->where('slot_start_time', $payload['slot_start_time'])
            ->where('slot_end_time', $payload['slot_end_time'])
            ->whereNotIn('registration_status', ['cancelled'])
            ->lockForUpdate()
            ->first();

        if ($conflict) {
            throw new VisitRegistrationException('Slot dokter yang dipilih sudah tidak tersedia. Pilih slot lain yang masih kosong.');
        }

        return [
            'doctor_id' => $schedule->doctor_id,
            'doctor_schedule_id' => $schedule->getKey(),
            'slot_start_time' => $slotStart->format('H:i:s'),
            'slot_end_time' => $slotEnd->format('H:i:s'),
        ];
    }

    private function buildRegistrationAttributes(
        array $payload,
        Patient $patient,
        int $patientBranchRecordId,
        int $branchId,
        int $counterId,
        int $sectionId,
        array $slot,
        ?VisitRegistration $existingRegistration = null,
    ): array {
        $visitType = $payload['visit_type'];

        return [
            'patient_id' => $patient->getKey(),
            'patient_branch_record_id' => $patientBranchRecordId,
            'branch_id' => $branchId,
            'counter_id' => $counterId,
            'section_id' => $sectionId,
            'doctor_id' => $slot['doctor_id'],
            'doctor_schedule_id' => $slot['doctor_schedule_id'],
            'visit_date' => Carbon::parse($payload['visit_date'])->toDateString(),
            'visit_type' => $visitType,
            'registration_status' => $visitType === 'booking' ? 'booked' : 'queued',
            'care_stage' => $visitType === 'booking'
                ? 'scheduled'
                : ($visitType === 'emergency' ? 'waiting_doctor' : 'waiting_nurse'),
            'vital_status' => $existingRegistration?->vital_status ?: 'pending',
            'booking_code' => $visitType === 'booking'
                ? ($existingRegistration?->booking_code ?: $this->nextBookingCode(Carbon::parse($payload['visit_date'])))
                : null,
            'slot_start_time' => $slot['slot_start_time'],
            'slot_end_time' => $slot['slot_end_time'],
            'notes' => $payload['notes'],
            'checked_in_at' => $visitType === 'booking'
                ? null
                : ($existingRegistration?->checked_in_at ?? now()),
            'queued_at' => $visitType === 'booking'
                ? null
                : $existingRegistration?->queued_at,
            'cancelled_at' => null,
        ];
    }

    private function registrationChanged(VisitRegistration $registration, array $attributes): bool
    {
        return $registration->patient_id !== $attributes['patient_id']
            || $registration->patient_branch_record_id !== $attributes['patient_branch_record_id']
            || $registration->branch_id !== $attributes['branch_id']
            || $registration->counter_id !== $attributes['counter_id']
            || $registration->section_id !== $attributes['section_id']
            || $registration->doctor_id !== $attributes['doctor_id']
            || $registration->doctor_schedule_id !== $attributes['doctor_schedule_id']
            || $registration->visit_date?->toDateString() !== $attributes['visit_date']
            || $registration->visit_type !== $attributes['visit_type']
            || $registration->registration_status !== $attributes['registration_status']
            || $registration->care_stage !== $attributes['care_stage']
            || $registration->vital_status !== $attributes['vital_status']
            || $registration->booking_code !== $attributes['booking_code']
            || $registration->slot_start_time !== $attributes['slot_start_time']
            || $registration->slot_end_time !== $attributes['slot_end_time']
            || $registration->notes !== $attributes['notes']
            || optional($registration->checked_in_at)->toIso8601String() !== optional($attributes['checked_in_at'])->toIso8601String()
            || optional($registration->queued_at)->toIso8601String() !== optional($attributes['queued_at'])->toIso8601String();
    }

    private function syncDoctorAssignment(VisitRegistration $registration, string $notes): void
    {
        $registration->loadMissing(['doctorAssignments', 'queueTicket', 'doctor', 'doctorSchedule']);

        $this->visitDoctorAssignmentService->assign(
            $registration,
            $registration->doctor,
            $notes,
        );
    }

    private function ensureQueueForImmediateVisit(VisitRegistration $registration): void
    {
        if (! in_array($registration->visit_type, ['same_day', 'emergency'], true)) {
            return;
        }

        $activeQueue = $registration->queueTicket()
            ->where('status', '!=', 'cancelled')
            ->lockForUpdate()
            ->first();

        if ($activeQueue) {
            return;
        }

        $this->queueService->createForRegistration($registration);
    }

    private function guardEditableRegistration(VisitRegistration $registration): void
    {
        $activeQueue = $registration->queueTicket()
            ->where('status', '!=', 'cancelled')
            ->lockForUpdate()
            ->first();

        if ($activeQueue) {
            throw new VisitRegistrationException('Registrasi yang sudah memiliki antrian aktif tidak bisa diubah dari halaman ini.', 409);
        }

        if ($registration->registration_status === 'completed') {
            throw new VisitRegistrationException('Registrasi yang sudah selesai tidak bisa diubah dari halaman ini.', 409);
        }
    }

    private function guardSameBranchContext(VisitRegistration $registration, int $activeBranchId): void
    {
        if ($registration->branch_id !== $activeBranchId) {
            throw new VisitRegistrationException('Registrasi ini tidak berada pada branch counter aktif.');
        }
    }

    private function overlapsDoctorLeave(Carbon $slotStart, Carbon $slotEnd, Collection $leaves): bool
    {
        foreach ($leaves as $leave) {
            if ($leave->leave_type === 'full_day') {
                return true;
            }

            $leaveStart = Carbon::parse($slotStart->toDateString() . ' ' . $leave->start_time);
            $leaveEnd = Carbon::parse($slotStart->toDateString() . ' ' . $leave->end_time);

            if ($slotStart->lt($leaveEnd) && $slotEnd->gt($leaveStart)) {
                return true;
            }
        }

        return false;
    }

    private function nextBookingCode(Carbon $visitDate): string
    {
        $prefix = 'BK-' . $visitDate->format('Ymd');

        $lastBooking = VisitRegistration::query()
            ->where('booking_code', 'like', $prefix . '-%')
            ->lockForUpdate()
            ->orderByDesc('booking_code')
            ->first();

        $lastNumber = 0;

        if ($lastBooking && preg_match('/(\d+)$/', (string) $lastBooking->booking_code, $matches) === 1) {
            $lastNumber = (int) $matches[1];
        }

        return sprintf('%s-%04d', $prefix, $lastNumber + 1);
    }

    private function abilities(): array
    {
        $user = auth()->user();

        return [
            'create' => $user?->hasRole('super-admin') || ($user?->can('create visit registration') ?? false),
            'edit' => $user?->hasRole('super-admin') || ($user?->can('edit visit registration') ?? false),
            'check_in' => $user?->hasRole('super-admin') || ($user?->can('edit visit registration') ?? false),
            'cancel' => $user?->hasRole('super-admin') || ($user?->can('delete visit registration') ?? false),
        ];
    }

    private function sortOptions(): array
    {
        return [
            'visit_date' => 'Tanggal kunjungan',
            'created_at' => 'Tanggal dibuat',
            'registration_status' => 'Status registrasi',
            'visit_type' => 'Tipe kunjungan',
            'checked_in_at' => 'Waktu check-in',
        ];
    }

    private function auditSnapshot(VisitRegistration $registration): array
    {
        return [
            'id' => $registration->getKey(),
            'patient_id' => $registration->patient_id,
            'patient_name' => $registration->patient?->full_name,
            'medical_record_no' => $registration->patientBranchRecord?->medical_record_no,
            'branch_id' => $registration->branch_id,
            'counter_id' => $registration->counter_id,
            'counter_code' => $registration->counter?->code,
            'section_id' => $registration->section_id,
            'section_name' => $registration->section?->name,
            'doctor_id' => $registration->doctor_id,
            'doctor_name' => $registration->doctor?->displayName(),
            'doctor_schedule_id' => $registration->doctor_schedule_id,
            'visit_date' => $registration->visit_date?->toDateString(),
            'visit_type' => $registration->visit_type,
            'registration_status' => $registration->registration_status,
            'care_stage' => $registration->care_stage,
            'vital_status' => $registration->vital_status,
            'booking_code' => $registration->booking_code,
            'slot_start_time' => $registration->slot_start_time,
            'slot_end_time' => $registration->slot_end_time,
            'queue_code' => $registration->queueTicket?->queue_code,
            'queue_status' => $registration->queueTicket?->status,
            'checked_in_at' => $registration->checked_in_at?->toIso8601String(),
            'queued_at' => $registration->queued_at?->toIso8601String(),
            'cancelled_at' => $registration->cancelled_at?->toIso8601String(),
        ];
    }

    private function indexRelations(): array
    {
        return [
            'patient:id,full_name,phone',
            'patientBranchRecord:id,patient_id,branch_id,medical_record_no',
            'section:id,branch_id,name,code,type,queue_prefix',
            'doctor:id,full_name,title_prefix,title_suffix',
            'counter:id,name,code',
            'queueTicket:id,visit_registration_id,queue_code,status',
            'doctorSchedule:id,room_label',
        ];
    }
}
