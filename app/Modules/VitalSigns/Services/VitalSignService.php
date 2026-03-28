<?php

namespace App\Modules\VitalSigns\Services;

use App\Models\Branch;
use App\Models\Section;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Models\VitalSignRecord;
use App\Modules\VitalSigns\Exceptions\VitalSignManagementException;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VitalSignService
{
    private const ALLOWED_SORTS = [
        'recorded_at',
        'created_at',
        'systolic_bp',
        'temperature_celsius',
        'spo2_percent',
    ];

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        return [
            'filters' => $filters,
            'vitalSigns' => $this->table($filters),
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'name', 'code']),
            'sectionOptions' => Section::query()
                ->with('branch:id,code')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'branch_id', 'name', 'code', 'type']),
            'visitOptions' => $this->visitOptions(),
            'abilities' => $this->abilities(),
            'sortOptions' => $this->sortOptions(),
            'perPageOptions' => [10, 25, 50, 100],
        ];
    }

    public function createVitalSign(array $payload, User $actor): array
    {
        return DB::transaction(function () use ($payload, $actor): array {
            $visit = $this->resolveVisit((int) $payload['visit_registration_id']);
            $attributes = $this->attributes($visit, $payload);

            $existingRecord = VitalSignRecord::query()
                ->where('visit_registration_id', $visit->getKey())
                ->where('recorded_at', $attributes['recorded_at'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existingRecord !== null && ! $this->recordChanged($existingRecord, $attributes)) {
                return [
                    'vital_sign' => $existingRecord->fresh($this->indexRelations()),
                    'changed' => false,
                ];
            }

            $record = VitalSignRecord::query()->create($attributes);

            $this->advanceVisitStage($visit);
            $record->load($this->indexRelations());

            $this->auditLogService->log(
                module: 'vital_sign_management',
                action: 'create',
                auditable: $record,
                description: sprintf('Vital signs visit %s dicatat oleh %s.', $visit->id, $actor->email),
                after: $this->auditSnapshot($record),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $visit->getKey(),
                    'branch_id' => $visit->branch_id,
                ],
            );

            return [
                'vital_sign' => $record,
                'changed' => true,
            ];
        });
    }

    public function updateVitalSign(VitalSignRecord $vitalSignRecord, array $payload, User $actor): array
    {
        return DB::transaction(function () use ($vitalSignRecord, $payload, $actor): array {
            $lockedRecord = VitalSignRecord::query()
                ->with($this->indexRelations())
                ->lockForUpdate()
                ->findOrFail($vitalSignRecord->getKey());

            if ((int) $payload['visit_registration_id'] !== (int) $lockedRecord->visit_registration_id) {
                throw new VitalSignManagementException(
                    'Vital signs yang sudah tercatat tidak boleh dipindahkan ke visit lain.',
                    409,
                );
            }

            $visit = $this->resolveVisit((int) $lockedRecord->visit_registration_id);
            $attributes = $this->attributes($visit, $payload, $lockedRecord);

            if (! $this->recordChanged($lockedRecord, $attributes)) {
                return [
                    'vital_sign' => $lockedRecord,
                    'changed' => false,
                ];
            }

            $before = $this->auditSnapshot($lockedRecord);

            $lockedRecord->fill($attributes);
            $lockedRecord->save();

            $this->advanceVisitStage($visit);
            $lockedRecord->load($this->indexRelations());

            $this->auditLogService->log(
                module: 'vital_sign_management',
                action: 'update',
                auditable: $lockedRecord,
                description: sprintf('Vital signs %s diperbarui oleh %s.', $lockedRecord->getKey(), $actor->email),
                before: $before,
                after: $this->auditSnapshot($lockedRecord),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $visit->getKey(),
                    'branch_id' => $visit->branch_id,
                ],
            );

            return [
                'vital_sign' => $lockedRecord,
                'changed' => true,
            ];
        });
    }

    public function vitalPayload(VitalSignRecord $vitalSignRecord): array
    {
        $vitalSignRecord->loadMissing($this->indexRelations());

        return [
            'id' => $vitalSignRecord->getKey(),
            'visit_registration_id' => $vitalSignRecord->visit_registration_id,
            'patient_id' => $vitalSignRecord->patient_id,
            'patient_name' => $vitalSignRecord->patient?->full_name,
            'patient_phone' => $vitalSignRecord->patient?->phone,
            'medical_record_no' => $vitalSignRecord->visitRegistration?->patientBranchRecord?->medical_record_no,
            'branch_id' => $vitalSignRecord->branch_id,
            'branch_code' => $vitalSignRecord->branch?->code,
            'section_id' => $vitalSignRecord->section_id,
            'section_name' => $vitalSignRecord->section?->name,
            'recorded_by_user_id' => $vitalSignRecord->recorded_by_user_id,
            'recorded_by_name' => $vitalSignRecord->recordedBy?->name,
            'systolic_bp' => $vitalSignRecord->systolic_bp,
            'diastolic_bp' => $vitalSignRecord->diastolic_bp,
            'temperature_celsius' => (float) $vitalSignRecord->temperature_celsius,
            'pulse_rate' => $vitalSignRecord->pulse_rate,
            'respiratory_rate' => $vitalSignRecord->respiratory_rate,
            'weight_kg' => (float) $vitalSignRecord->weight_kg,
            'height_cm' => (float) $vitalSignRecord->height_cm,
            'spo2_percent' => $vitalSignRecord->spo2_percent,
            'bmi' => $vitalSignRecord->bmi !== null ? (float) $vitalSignRecord->bmi : null,
            'notes' => $vitalSignRecord->notes,
            'recorded_at' => $vitalSignRecord->recorded_at?->toIso8601String(),
            'visit' => [
                'id' => $vitalSignRecord->visitRegistration?->getKey(),
                'care_stage' => $vitalSignRecord->visitRegistration?->care_stage,
                'vital_status' => $vitalSignRecord->visitRegistration?->vital_status,
            ],
        ];
    }

    private function table(array $filters): LengthAwarePaginator
    {
        return VitalSignRecord::query()
            ->with($this->indexRelations())
            ->searchForManagement($filters['search'])
            ->filterDate($filters['date'])
            ->filterBranch($filters['branch'])
            ->filterSection($filters['section'])
            ->orderByAllowed($filters['sort_by'], $filters['sort_direction'], self::ALLOWED_SORTS)
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    private function visitOptions(): Collection
    {
        return VisitRegistration::query()
            ->with([
                'patient:id,full_name,phone',
                'patientBranchRecord:id,patient_id,branch_id,medical_record_no',
                'section:id,name,code,type',
                'latestVitalSign',
                'medicalRecord:id,visit_registration_id,status,doctor_id',
            ])
            ->whereDate('visit_date', '<=', now()->toDateString())
            ->whereNotIn('care_stage', ['cancelled', 'completed', 'ready_for_checkout'])
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->limit(150)
            ->get(['id', 'patient_id', 'patient_branch_record_id', 'section_id', 'visit_date', 'visit_type', 'care_stage', 'vital_status']);
    }

    private function resolveVisit(int $visitRegistrationId): VisitRegistration
    {
        $visit = VisitRegistration::query()
            ->with('medicalRecord:id,visit_registration_id,status')
            ->lockForUpdate()
            ->find($visitRegistrationId);

        if ($visit === null) {
            throw new VitalSignManagementException('Visit registration tidak ditemukan.', 404, 'visit_registration_id');
        }

        if ($visit->visit_date->isFuture()) {
            throw new VitalSignManagementException(
                'Vital signs hanya bisa diinput untuk visit hari ini atau yang sudah berjalan.',
                422,
                'visit_registration_id',
            );
        }

        if (in_array($visit->care_stage, ['cancelled', 'completed', 'ready_for_checkout'], true)) {
            throw new VitalSignManagementException(
                'Visit pada tahap ini tidak bisa diinput atau diubah vital signs lagi.',
                409,
                'visit_registration_id',
            );
        }

        if (in_array($visit->medicalRecord?->status, ['final', 'reopen_requested'], true)) {
            throw new VitalSignManagementException(
                'Vital signs tidak bisa diubah ketika rekam medis sudah final atau menunggu reopen.',
                409,
                'vital_sign_management',
            );
        }

        return $visit;
    }

    private function attributes(VisitRegistration $visit, array $payload, ?VitalSignRecord $existingRecord = null): array
    {
        $recordedAt = filled($payload['recorded_at'])
            ? Carbon::parse($payload['recorded_at'])
            : now();
        $heightMeters = ((float) $payload['height_cm']) / 100;
        $bmi = $heightMeters > 0
            ? round(((float) $payload['weight_kg']) / ($heightMeters * $heightMeters), 2)
            : null;

        return [
            'visit_registration_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'recorded_by_user_id' => $existingRecord?->recorded_by_user_id ?: auth()->id(),
            'systolic_bp' => $payload['systolic_bp'],
            'diastolic_bp' => $payload['diastolic_bp'],
            'temperature_celsius' => $payload['temperature_celsius'],
            'pulse_rate' => $payload['pulse_rate'],
            'respiratory_rate' => $payload['respiratory_rate'],
            'weight_kg' => $payload['weight_kg'],
            'height_cm' => $payload['height_cm'],
            'spo2_percent' => $payload['spo2_percent'],
            'bmi' => $bmi,
            'notes' => $payload['notes'],
            'recorded_at' => $recordedAt,
        ];
    }

    private function recordChanged(VitalSignRecord $record, array $attributes): bool
    {
        return $record->visit_registration_id !== $attributes['visit_registration_id']
            || $record->patient_id !== $attributes['patient_id']
            || $record->branch_id !== $attributes['branch_id']
            || $record->section_id !== $attributes['section_id']
            || $record->systolic_bp !== $attributes['systolic_bp']
            || $record->diastolic_bp !== $attributes['diastolic_bp']
            || (float) $record->temperature_celsius !== (float) $attributes['temperature_celsius']
            || $record->pulse_rate !== $attributes['pulse_rate']
            || $record->respiratory_rate !== $attributes['respiratory_rate']
            || (float) $record->weight_kg !== (float) $attributes['weight_kg']
            || (float) $record->height_cm !== (float) $attributes['height_cm']
            || $record->spo2_percent !== $attributes['spo2_percent']
            || (float) ($record->bmi ?? 0) !== (float) ($attributes['bmi'] ?? 0)
            || $record->notes !== $attributes['notes']
            || optional($record->recorded_at)->toIso8601String() !== $attributes['recorded_at']?->toIso8601String();
    }

    private function advanceVisitStage(VisitRegistration $visit): void
    {
        $nextStage = match ($visit->care_stage) {
            'scheduled', 'waiting_nurse' => 'waiting_doctor',
            'waiting_doctor', 'in_consultation' => $visit->care_stage,
            default => 'waiting_doctor',
        };

        $visit->forceFill([
            'vital_status' => 'completed',
            'care_stage' => $nextStage,
        ])->save();
    }

    private function abilities(): array
    {
        $user = auth()->user();

        return [
            'create' => $user?->hasRole('super-admin') || ($user?->can('create vital sign management') ?? false),
            'edit' => $user?->hasRole('super-admin') || ($user?->can('edit vital sign management') ?? false),
        ];
    }

    private function sortOptions(): array
    {
        return [
            'recorded_at' => 'Waktu pengukuran',
            'created_at' => 'Waktu dibuat',
            'systolic_bp' => 'Systolic BP',
            'temperature_celsius' => 'Temperature',
            'spo2_percent' => 'SpO2',
        ];
    }

    private function auditSnapshot(VitalSignRecord $record): array
    {
        return [
            'id' => $record->getKey(),
            'visit_registration_id' => $record->visit_registration_id,
            'patient_id' => $record->patient_id,
            'patient_name' => $record->patient?->full_name,
            'medical_record_no' => $record->visitRegistration?->patientBranchRecord?->medical_record_no,
            'branch_id' => $record->branch_id,
            'branch_code' => $record->branch?->code,
            'section_id' => $record->section_id,
            'section_name' => $record->section?->name,
            'recorded_by_user_id' => $record->recorded_by_user_id,
            'recorded_by_name' => $record->recordedBy?->name,
            'systolic_bp' => $record->systolic_bp,
            'diastolic_bp' => $record->diastolic_bp,
            'temperature_celsius' => (float) $record->temperature_celsius,
            'pulse_rate' => $record->pulse_rate,
            'respiratory_rate' => $record->respiratory_rate,
            'weight_kg' => (float) $record->weight_kg,
            'height_cm' => (float) $record->height_cm,
            'spo2_percent' => $record->spo2_percent,
            'bmi' => $record->bmi !== null ? (float) $record->bmi : null,
            'notes' => $record->notes,
            'recorded_at' => $record->recorded_at?->toIso8601String(),
            'visit_care_stage' => $record->visitRegistration?->care_stage,
            'visit_vital_status' => $record->visitRegistration?->vital_status,
        ];
    }

    private function indexRelations(): array
    {
        return [
            'visitRegistration:id,patient_branch_record_id,visit_date,visit_type,care_stage,vital_status',
            'visitRegistration.patientBranchRecord:id,medical_record_no',
            'patient:id,full_name,phone',
            'branch:id,name,code',
            'section:id,name,code,type',
            'recordedBy:id,name',
        ];
    }
}
