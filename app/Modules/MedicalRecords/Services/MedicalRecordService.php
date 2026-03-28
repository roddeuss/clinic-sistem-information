<?php

namespace App\Modules\MedicalRecords\Services;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Icd10Code;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordDiagnosis;
use App\Models\Section;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Modules\MedicalRecords\Exceptions\MedicalRecordManagementException;
use App\Services\AuditLogService;
use App\Services\ClinicalBillingService;
use App\Services\ClinicalWorkflowService;
use App\Services\VisitDoctorAssignmentService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MedicalRecordService
{
    private const ALLOWED_SORTS = [
        'visit_date',
        'created_at',
        'care_stage',
        'vital_status',
    ];

    public function __construct(
        private readonly VisitDoctorAssignmentService $visitDoctorAssignmentService,
        private readonly ClinicalWorkflowService $clinicalWorkflowService,
        private readonly ClinicalBillingService $clinicalBillingService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        return [
            'filters' => $filters,
            'visits' => $this->table($filters),
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'name', 'code']),
            'sectionOptions' => Section::query()
                ->with('branch:id,code')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'branch_id', 'name', 'code', 'type']),
            'visitOptions' => $this->visitOptions(),
            'icd10Options' => Icd10Code::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->limit(250)
                ->get(['id', 'code', 'name_en', 'name_id']),
            'abilities' => $this->abilities(),
            'sortOptions' => $this->sortOptions(),
            'perPageOptions' => [10, 25, 50, 100],
        ];
    }

    public function createMedicalRecord(array $payload, User $actor): array
    {
        return DB::transaction(function () use ($payload, $actor): array {
            $visit = $this->resolveVisit((int) $payload['visit_registration_id']);
            $doctor = $this->resolveDoctor($visit, (int) $payload['doctor_id']);

            $existingRecord = MedicalRecord::query()
                ->with($this->recordRelations())
                ->where('visit_registration_id', $visit->getKey())
                ->lockForUpdate()
                ->first();

            if ($existingRecord !== null) {
                if ($this->recordMatchesDesiredState($existingRecord, $visit, $doctor, $payload, false)) {
                    return [
                        'medical_record' => $existingRecord,
                        'changed' => false,
                    ];
                }

                throw new MedicalRecordManagementException(
                    'Visit ini sudah memiliki rekam medis. Gunakan update untuk mengubah SOAP yang ada.',
                    409,
                    'visit_registration_id',
                );
            }

            $record = MedicalRecord::query()->create($this->recordAttributes($visit, $doctor, $payload));

            $this->visitDoctorAssignmentService->assign($visit->fresh(), $doctor, 'Assigned from medical record.');
            $this->syncDiagnoses($record, $payload);

            $record = $this->applySubmitAction(
                $record->fresh($this->recordRelations()),
                $payload['submit_action'],
                true,
                $actor,
            );

            $this->auditLogService->log(
                module: 'medical_record_management',
                action: 'create',
                auditable: $record,
                description: sprintf('Medical record %s dibuat oleh %s.', $record->getKey(), $actor->email),
                after: $this->auditSnapshot($record),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $record->visit_registration_id,
                    'branch_id' => $record->branch_id,
                    'submit_action' => $payload['submit_action'],
                ],
            );

            return [
                'medical_record' => $record,
                'changed' => true,
            ];
        });
    }

    public function updateMedicalRecord(MedicalRecord $medicalRecord, array $payload, User $actor): array
    {
        return DB::transaction(function () use ($medicalRecord, $payload, $actor): array {
            $lockedRecord = MedicalRecord::query()
                ->with($this->recordRelations())
                ->lockForUpdate()
                ->find($medicalRecord->getKey());

            if ($lockedRecord === null) {
                throw new MedicalRecordManagementException('Rekam medis tidak ditemukan.', 404);
            }

            if ((int) $payload['visit_registration_id'] !== (int) $lockedRecord->visit_registration_id) {
                throw new MedicalRecordManagementException(
                    'Rekam medis yang sudah tercatat tidak boleh dipindahkan ke visit lain.',
                    409,
                    'visit_registration_id',
                );
            }

            if (! $lockedRecord->isEditable()) {
                throw new MedicalRecordManagementException(
                    'Rekam medis final atau menunggu approval reopen tidak bisa diedit langsung.',
                    409,
                );
            }

            $visit = $this->resolveVisit((int) $lockedRecord->visit_registration_id);
            $doctor = $this->resolveDoctor($visit, (int) $payload['doctor_id']);

            if (! $this->recordChanged($lockedRecord, $visit, $doctor, $payload)) {
                return [
                    'medical_record' => $lockedRecord,
                    'changed' => false,
                ];
            }

            $before = $this->auditSnapshot($lockedRecord);

            $lockedRecord->fill($this->recordAttributes($visit, $doctor, $payload));
            $lockedRecord->save();

            $this->visitDoctorAssignmentService->assign($visit->fresh(), $doctor, 'Re-assigned from medical record update.');
            $this->syncDiagnoses($lockedRecord, $payload);

            $lockedRecord = $this->applySubmitAction(
                $lockedRecord->fresh($this->recordRelations()),
                $payload['submit_action'],
                false,
                $actor,
            );

            $this->auditLogService->log(
                module: 'medical_record_management',
                action: 'update',
                auditable: $lockedRecord,
                description: sprintf('Medical record %s diperbarui oleh %s.', $lockedRecord->getKey(), $actor->email),
                before: $before,
                after: $this->auditSnapshot($lockedRecord),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $lockedRecord->visit_registration_id,
                    'branch_id' => $lockedRecord->branch_id,
                    'submit_action' => $payload['submit_action'],
                ],
            );

            return [
                'medical_record' => $lockedRecord,
                'changed' => true,
            ];
        });
    }

    public function requestReopen(MedicalRecord $medicalRecord, string $reason, User $actor): array
    {
        return DB::transaction(function () use ($medicalRecord, $reason, $actor): array {
            $lockedRecord = MedicalRecord::query()
                ->with($this->recordRelations())
                ->lockForUpdate()
                ->find($medicalRecord->getKey());

            if ($lockedRecord === null) {
                throw new MedicalRecordManagementException('Rekam medis tidak ditemukan.', 404);
            }

            if ($this->isAdmin($actor)) {
                if ($lockedRecord->status === 'reopened') {
                    return [
                        'medical_record' => $lockedRecord,
                        'changed' => false,
                        'mode' => 'direct',
                    ];
                }

                if (! $lockedRecord->isFinalized()) {
                    throw new MedicalRecordManagementException('Hanya rekam medis final yang bisa direopen.');
                }

                $before = $this->auditSnapshot($lockedRecord);

                $lockedRecord->forceFill([
                    'status' => 'reopened',
                    'reopen_requested_at' => null,
                    'reopen_requested_by_user_id' => null,
                    'reopen_request_reason' => null,
                    'reopened_at' => now(),
                    'reopened_by_user_id' => $actor->getKey(),
                    'reopen_approved_by_user_id' => $actor->getKey(),
                    'reopen_approval_reason' => $reason,
                ])->save();

                $lockedRecord = $this->refreshVisitForRecord($lockedRecord);
                $this->writeDomainAudit($lockedRecord, 'reopened', $reason, $actor);

                $this->auditLogService->log(
                    module: 'medical_record_management',
                    action: 'direct_reopen',
                    auditable: $lockedRecord,
                    description: sprintf('Medical record %s direopen langsung oleh %s.', $lockedRecord->getKey(), $actor->email),
                    before: $before,
                    after: $this->auditSnapshot($lockedRecord),
                    meta: [
                        'actor_user_id' => $actor->getKey(),
                        'visit_registration_id' => $lockedRecord->visit_registration_id,
                        'branch_id' => $lockedRecord->branch_id,
                    ],
                );

                return [
                    'medical_record' => $lockedRecord,
                    'changed' => true,
                    'mode' => 'direct',
                ];
            }

            if (! $actor->hasRole('doctor')) {
                throw new MedicalRecordManagementException(
                    'Hanya dokter atau admin yang bisa meminta reopen rekam medis.',
                    403,
                );
            }

            if ($lockedRecord->status === 'reopen_requested') {
                return [
                    'medical_record' => $lockedRecord,
                    'changed' => false,
                    'mode' => 'request',
                ];
            }

            if (! $lockedRecord->isFinalized()) {
                throw new MedicalRecordManagementException('Hanya rekam medis final yang bisa direopen.');
            }

            $before = $this->auditSnapshot($lockedRecord);

            $lockedRecord->forceFill([
                'status' => 'reopen_requested',
                'reopen_requested_at' => now(),
                'reopen_requested_by_user_id' => $actor->getKey(),
                'reopen_request_reason' => $reason,
            ])->save();

            $lockedRecord = $this->refreshVisitForRecord($lockedRecord);
            $this->writeDomainAudit($lockedRecord, 'reopen_requested', $reason, $actor);

            $this->auditLogService->log(
                module: 'medical_record_management',
                action: 'request_reopen',
                auditable: $lockedRecord,
                description: sprintf('Permintaan reopen medical record %s diajukan oleh %s.', $lockedRecord->getKey(), $actor->email),
                before: $before,
                after: $this->auditSnapshot($lockedRecord),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $lockedRecord->visit_registration_id,
                    'branch_id' => $lockedRecord->branch_id,
                ],
            );

            return [
                'medical_record' => $lockedRecord,
                'changed' => true,
                'mode' => 'request',
            ];
        });
    }

    public function approveReopen(MedicalRecord $medicalRecord, string $reason, User $actor): array
    {
        return DB::transaction(function () use ($medicalRecord, $reason, $actor): array {
            if (! $this->isAdmin($actor)) {
                throw new MedicalRecordManagementException(
                    'Approval reopen hanya bisa dilakukan admin klinik.',
                    403,
                );
            }

            $lockedRecord = MedicalRecord::query()
                ->with($this->recordRelations())
                ->lockForUpdate()
                ->find($medicalRecord->getKey());

            if ($lockedRecord === null) {
                throw new MedicalRecordManagementException('Rekam medis tidak ditemukan.', 404);
            }

            if ($lockedRecord->status === 'reopened') {
                return [
                    'medical_record' => $lockedRecord,
                    'changed' => false,
                ];
            }

            if (! $lockedRecord->isAwaitingReopenApproval()) {
                throw new MedicalRecordManagementException(
                    'Tidak ada permintaan reopen yang menunggu approval.',
                    409,
                );
            }

            $before = $this->auditSnapshot($lockedRecord);

            $lockedRecord->forceFill([
                'status' => 'reopened',
                'reopened_at' => now(),
                'reopened_by_user_id' => $actor->getKey(),
                'reopen_approved_by_user_id' => $actor->getKey(),
                'reopen_approval_reason' => $reason,
            ])->save();

            $lockedRecord = $this->refreshVisitForRecord($lockedRecord);
            $this->writeDomainAudit($lockedRecord, 'reopen_approved', $reason, $actor);

            $this->auditLogService->log(
                module: 'medical_record_management',
                action: 'approve_reopen',
                auditable: $lockedRecord,
                description: sprintf('Permintaan reopen medical record %s disetujui oleh %s.', $lockedRecord->getKey(), $actor->email),
                before: $before,
                after: $this->auditSnapshot($lockedRecord),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $lockedRecord->visit_registration_id,
                    'branch_id' => $lockedRecord->branch_id,
                ],
            );

            return [
                'medical_record' => $lockedRecord,
                'changed' => true,
            ];
        });
    }

    public function medicalRecordPayload(MedicalRecord $medicalRecord): array
    {
        $medicalRecord->loadMissing($this->recordRelations());

        $primaryDiagnosis = $medicalRecord->diagnoses->firstWhere('diagnosis_type', 'primary');
        $secondaryDiagnoses = $medicalRecord->diagnoses
            ->where('diagnosis_type', 'secondary')
            ->map(fn (MedicalRecordDiagnosis $diagnosis): array => [
                'id' => $diagnosis->getKey(),
                'icd10_code_id' => $diagnosis->icd10_code_id,
                'code' => $diagnosis->icd10Code?->code,
                'name_en' => $diagnosis->icd10Code?->name_en,
                'name_id' => $diagnosis->icd10Code?->name_id,
                'sort_order' => $diagnosis->sort_order,
            ])
            ->values()
            ->all();

        return [
            'id' => $medicalRecord->getKey(),
            'visit_registration_id' => $medicalRecord->visit_registration_id,
            'patient_id' => $medicalRecord->patient_id,
            'patient_name' => $medicalRecord->patient?->full_name,
            'medical_record_no' => $medicalRecord->visitRegistration?->patientBranchRecord?->medical_record_no,
            'branch_id' => $medicalRecord->branch_id,
            'branch_code' => $medicalRecord->branch?->code,
            'section_id' => $medicalRecord->section_id,
            'section_name' => $medicalRecord->section?->name,
            'doctor_id' => $medicalRecord->doctor_id,
            'doctor_name' => $medicalRecord->doctor?->displayName(),
            'subjective' => $medicalRecord->subjective,
            'objective' => $medicalRecord->objective,
            'assessment' => $medicalRecord->assessment,
            'plan' => $medicalRecord->plan,
            'diagnosis_notes' => $medicalRecord->diagnosis_notes,
            'status' => $medicalRecord->status,
            'primary_diagnosis' => $primaryDiagnosis ? [
                'icd10_code_id' => $primaryDiagnosis->icd10_code_id,
                'code' => $primaryDiagnosis->icd10Code?->code,
                'name_en' => $primaryDiagnosis->icd10Code?->name_en,
                'name_id' => $primaryDiagnosis->icd10Code?->name_id,
            ] : null,
            'secondary_diagnoses' => $secondaryDiagnoses,
            'finalized_at' => $medicalRecord->finalized_at?->toIso8601String(),
            'reopen_requested_at' => $medicalRecord->reopen_requested_at?->toIso8601String(),
            'reopened_at' => $medicalRecord->reopened_at?->toIso8601String(),
            'visit' => [
                'id' => $medicalRecord->visitRegistration?->getKey(),
                'visit_date' => $medicalRecord->visitRegistration?->visit_date?->toDateString(),
                'care_stage' => $medicalRecord->visitRegistration?->care_stage,
                'vital_status' => $medicalRecord->visitRegistration?->vital_status,
            ],
        ];
    }

    public function visitPayload(VisitRegistration $visit): array
    {
        $visit->loadMissing($this->visitRelations());

        return [
            'id' => $visit->getKey(),
            'patient_id' => $visit->patient_id,
            'patient_name' => $visit->patient?->full_name,
            'patient_phone' => $visit->patient?->phone,
            'medical_record_no' => $visit->patientBranchRecord?->medical_record_no,
            'branch_id' => $visit->branch_id,
            'branch_code' => $visit->branch?->code,
            'section_id' => $visit->section_id,
            'section_name' => $visit->section?->name,
            'doctor_id' => $visit->doctor_id,
            'doctor_name' => $visit->medicalRecord?->doctor?->displayName() ?? $visit->doctor?->displayName(),
            'visit_date' => $visit->visit_date?->toDateString(),
            'care_stage' => $visit->care_stage,
            'vital_status' => $visit->vital_status,
            'latest_vital' => $visit->latestVitalSign ? [
                'id' => $visit->latestVitalSign->getKey(),
                'systolic_bp' => $visit->latestVitalSign->systolic_bp,
                'diastolic_bp' => $visit->latestVitalSign->diastolic_bp,
                'temperature_celsius' => (float) $visit->latestVitalSign->temperature_celsius,
                'pulse_rate' => $visit->latestVitalSign->pulse_rate,
                'spo2_percent' => $visit->latestVitalSign->spo2_percent,
                'recorded_at' => $visit->latestVitalSign->recorded_at?->toIso8601String(),
            ] : null,
            'medical_record' => $visit->medicalRecord
                ? $this->medicalRecordPayload($visit->medicalRecord)
                : null,
        ];
    }

    private function table(array $filters): LengthAwarePaginator
    {
        return VisitRegistration::query()
            ->with($this->visitRelations())
            ->whereDate('visit_date', '<=', now()->toDateString())
            ->searchForManagement($filters['search'])
            ->filterDate($filters['date'])
            ->filterSection($filters['section'])
            ->when($filters['branch'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->when($filters['status'] !== '', function (Builder $query) use ($filters): void {
                match ($filters['status']) {
                    'pending' => $query->whereDoesntHave('medicalRecord'),
                    default => $query->whereHas('medicalRecord', fn (Builder $recordQuery) => $recordQuery->where('status', $filters['status'])),
                };
            })
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
                'section.doctors:id,full_name,title_prefix,title_suffix,is_active',
                'latestVitalSign',
                'medicalRecord:id,visit_registration_id,status,doctor_id',
            ])
            ->whereDate('visit_date', '<=', now()->toDateString())
            ->whereNotIn('care_stage', ['cancelled', 'completed'])
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->limit(150)
            ->get(['id', 'patient_id', 'patient_branch_record_id', 'section_id', 'doctor_id', 'visit_date', 'care_stage', 'vital_status']);
    }

    private function resolveVisit(int $visitRegistrationId): VisitRegistration
    {
        $visit = VisitRegistration::query()
            ->with([
                'patient:id,full_name,phone',
                'patientBranchRecord:id,patient_id,branch_id,medical_record_no',
                'section:id,branch_id,name,code,type',
                'section.doctors:id,full_name,title_prefix,title_suffix,is_active',
                'medicalRecord:id,visit_registration_id,status',
            ])
            ->lockForUpdate()
            ->find($visitRegistrationId);

        if ($visit === null) {
            throw new MedicalRecordManagementException('Visit registration tidak ditemukan.', 404, 'visit_registration_id');
        }

        if ($visit->visit_date->isFuture()) {
            throw new MedicalRecordManagementException(
                'Rekam medis hanya bisa dibuat untuk visit hari ini atau yang sudah berjalan.',
                422,
                'visit_registration_id',
            );
        }

        if (in_array($visit->care_stage, ['cancelled', 'completed'], true) || $visit->registration_status === 'cancelled') {
            throw new MedicalRecordManagementException(
                'Visit yang dibatalkan atau sudah selesai tidak bisa diubah rekam medisnya.',
                409,
                'visit_registration_id',
            );
        }

        return $visit;
    }

    private function resolveDoctor(VisitRegistration $visit, int $doctorId): Doctor
    {
        $doctor = Doctor::query()
            ->where('is_active', true)
            ->find($doctorId);

        if ($doctor === null) {
            throw new MedicalRecordManagementException('Dokter aktif yang dipilih tidak ditemukan.', 404, 'doctor_id');
        }

        $allowedDoctorIds = $visit->section?->doctors
            ?->where('is_active', true)
            ->pluck('id')
            ->all() ?? [];

        if (! in_array($doctor->getKey(), $allowedDoctorIds, true)) {
            throw new MedicalRecordManagementException(
                'Dokter yang dipilih harus terhubung ke section visit yang sama.',
                422,
                'doctor_id',
            );
        }

        return $doctor;
    }

    private function recordAttributes(VisitRegistration $visit, Doctor $doctor, array $payload): array
    {
        return [
            'visit_registration_id' => $visit->getKey(),
            'patient_id' => $visit->patient_id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'doctor_id' => $doctor->getKey(),
            'subjective' => $payload['subjective'],
            'objective' => $payload['objective'],
            'assessment' => $payload['assessment'],
            'plan' => $payload['plan'],
            'diagnosis_notes' => $payload['diagnosis_notes'],
        ];
    }

    private function recordMatchesDesiredState(
        MedicalRecord $medicalRecord,
        VisitRegistration $visit,
        Doctor $doctor,
        array $payload,
        bool $preserveReopenedDraftStatus,
    ): bool {
        $attributes = $this->recordAttributes($visit, $doctor, $payload);
        $desiredStatus = $this->desiredStatus($medicalRecord, $payload['submit_action'], $preserveReopenedDraftStatus);

        return ! $this->recordAttributesChanged($medicalRecord, $attributes)
            && ! $this->diagnosesChanged($medicalRecord, $payload)
            && $medicalRecord->status === $desiredStatus;
    }

    private function recordChanged(
        MedicalRecord $medicalRecord,
        VisitRegistration $visit,
        Doctor $doctor,
        array $payload,
    ): bool {
        return ! $this->recordMatchesDesiredState($medicalRecord, $visit, $doctor, $payload, true);
    }

    private function recordAttributesChanged(MedicalRecord $medicalRecord, array $attributes): bool
    {
        return $medicalRecord->visit_registration_id !== $attributes['visit_registration_id']
            || $medicalRecord->patient_id !== $attributes['patient_id']
            || $medicalRecord->branch_id !== $attributes['branch_id']
            || $medicalRecord->section_id !== $attributes['section_id']
            || $medicalRecord->doctor_id !== $attributes['doctor_id']
            || $medicalRecord->subjective !== $attributes['subjective']
            || $medicalRecord->objective !== $attributes['objective']
            || $medicalRecord->assessment !== $attributes['assessment']
            || $medicalRecord->plan !== $attributes['plan']
            || $medicalRecord->diagnosis_notes !== $attributes['diagnosis_notes'];
    }

    private function diagnosesChanged(MedicalRecord $medicalRecord, array $payload): bool
    {
        $current = $medicalRecord->diagnoses
            ->map(fn (MedicalRecordDiagnosis $diagnosis): array => [
                'icd10_code_id' => (int) $diagnosis->icd10_code_id,
                'diagnosis_type' => $diagnosis->diagnosis_type,
                'sort_order' => (int) $diagnosis->sort_order,
            ])
            ->values()
            ->all();

        return $current !== $this->diagnosisRows($payload);
    }

    private function syncDiagnoses(MedicalRecord $medicalRecord, array $payload): void
    {
        $rows = $this->diagnosisRows($payload);

        $medicalRecord->diagnoses()->delete();

        if ($rows === []) {
            return;
        }

        $timestamp = now();

        MedicalRecordDiagnosis::query()->insert(array_map(
            fn (array $row): array => [
                'medical_record_id' => $medicalRecord->getKey(),
                'icd10_code_id' => $row['icd10_code_id'],
                'diagnosis_type' => $row['diagnosis_type'],
                'sort_order' => $row['sort_order'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            $rows
        ));
    }

    private function diagnosisRows(array $payload): array
    {
        $rows = [];

        if (! empty($payload['primary_icd10_id'])) {
            $rows[] = [
                'icd10_code_id' => (int) $payload['primary_icd10_id'],
                'diagnosis_type' => 'primary',
                'sort_order' => 1,
            ];
        }

        foreach (array_values($payload['secondary_icd10_ids'] ?? []) as $index => $icd10Id) {
            $rows[] = [
                'icd10_code_id' => (int) $icd10Id,
                'diagnosis_type' => 'secondary',
                'sort_order' => $index + 1,
            ];
        }

        return $rows;
    }

    private function applySubmitAction(
        MedicalRecord $medicalRecord,
        string $submitAction,
        bool $isNewRecord,
        User $actor,
    ): MedicalRecord {
        $visit = VisitRegistration::query()
            ->with($this->workflowVisitRelations())
            ->lockForUpdate()
            ->findOrFail($medicalRecord->visit_registration_id);

        if ($submitAction === 'final') {
            $medicalRecord->forceFill([
                'status' => 'final',
                'finalized_at' => now(),
                'finalized_by_user_id' => $actor->getKey(),
            ])->save();

            $medicalRecord = $medicalRecord->fresh($this->recordRelations());

            $this->writeDomainAudit($medicalRecord, 'finalized', 'SOAP finalized.', $actor);
            $this->clinicalBillingService->syncVisit($visit->fresh($this->billingVisitRelations()));
            $this->clinicalWorkflowService->refreshVisit($visit->fresh($this->workflowVisitRelations()));

            return $medicalRecord->fresh($this->recordRelations());
        }

        $medicalRecord->forceFill([
            'status' => $medicalRecord->status === 'reopened' ? 'reopened' : 'draft',
        ])->save();

        $visit->forceFill([
            'care_stage' => 'in_consultation',
        ])->save();

        $medicalRecord = $medicalRecord->fresh($this->recordRelations());

        $this->writeDomainAudit(
            $medicalRecord,
            $isNewRecord ? 'draft_created' : 'draft_saved',
            'SOAP draft saved.',
            $actor,
        );

        return $medicalRecord;
    }

    private function refreshVisitForRecord(MedicalRecord $medicalRecord): MedicalRecord
    {
        $visit = VisitRegistration::query()
            ->with($this->workflowVisitRelations())
            ->lockForUpdate()
            ->findOrFail($medicalRecord->visit_registration_id);

        $this->clinicalWorkflowService->refreshVisit($visit);

        return $medicalRecord->fresh($this->recordRelations());
    }

    private function writeDomainAudit(MedicalRecord $medicalRecord, string $action, ?string $notes, User $actor): void
    {
        $medicalRecord->audits()->create([
            'action' => $action,
            'notes' => $notes,
            'snapshot' => $this->auditSnapshot($medicalRecord),
            'performed_by_user_id' => $actor->getKey(),
        ]);
    }

    private function desiredStatus(MedicalRecord $medicalRecord, string $submitAction, bool $preserveReopenedDraftStatus): string
    {
        if ($submitAction === 'final') {
            return 'final';
        }

        if ($preserveReopenedDraftStatus && $medicalRecord->status === 'reopened') {
            return 'reopened';
        }

        return 'draft';
    }

    private function abilities(): array
    {
        $user = auth()->user();

        return [
            'create' => $user?->hasRole('super-admin') || ($user?->can('create medical record management') ?? false),
            'edit' => $user?->hasRole('super-admin') || ($user?->can('edit medical record management') ?? false),
            'approve_reopen' => $user?->hasAnyRole(['super-admin', 'clinic-admin']) ?? false,
            'direct_reopen' => $user?->hasAnyRole(['super-admin', 'clinic-admin']) ?? false,
        ];
    }

    private function sortOptions(): array
    {
        return [
            'visit_date' => 'Tanggal visit',
            'created_at' => 'Waktu registrasi',
            'care_stage' => 'Tahap perawatan',
            'vital_status' => 'Status vital',
        ];
    }

    private function auditSnapshot(MedicalRecord $medicalRecord): array
    {
        $medicalRecord->loadMissing($this->recordRelations());

        return [
            'id' => $medicalRecord->getKey(),
            'visit_registration_id' => $medicalRecord->visit_registration_id,
            'patient_id' => $medicalRecord->patient_id,
            'patient_name' => $medicalRecord->patient?->full_name,
            'medical_record_no' => $medicalRecord->visitRegistration?->patientBranchRecord?->medical_record_no,
            'branch_id' => $medicalRecord->branch_id,
            'branch_code' => $medicalRecord->branch?->code,
            'section_id' => $medicalRecord->section_id,
            'section_name' => $medicalRecord->section?->name,
            'doctor_id' => $medicalRecord->doctor_id,
            'doctor_name' => $medicalRecord->doctor?->displayName(),
            'subjective' => $medicalRecord->subjective,
            'objective' => $medicalRecord->objective,
            'assessment' => $medicalRecord->assessment,
            'plan' => $medicalRecord->plan,
            'diagnosis_notes' => $medicalRecord->diagnosis_notes,
            'status' => $medicalRecord->status,
            'finalized_at' => $medicalRecord->finalized_at?->toIso8601String(),
            'reopen_requested_at' => $medicalRecord->reopen_requested_at?->toIso8601String(),
            'reopened_at' => $medicalRecord->reopened_at?->toIso8601String(),
            'diagnoses' => $medicalRecord->diagnoses
                ->map(fn (MedicalRecordDiagnosis $diagnosis): array => [
                    'type' => $diagnosis->diagnosis_type,
                    'sort_order' => $diagnosis->sort_order,
                    'code' => $diagnosis->icd10Code?->code,
                    'name_en' => $diagnosis->icd10Code?->name_en,
                    'name_id' => $diagnosis->icd10Code?->name_id,
                ])
                ->values()
                ->all(),
            'visit_care_stage' => $medicalRecord->visitRegistration?->care_stage,
            'visit_vital_status' => $medicalRecord->visitRegistration?->vital_status,
        ];
    }

    private function recordRelations(): array
    {
        return [
            'visitRegistration:id,patient_branch_record_id,visit_date,care_stage,vital_status',
            'visitRegistration.patientBranchRecord:id,medical_record_no',
            'patient:id,full_name,phone',
            'branch:id,name,code',
            'section:id,name,code,type',
            'doctor:id,full_name,title_prefix,title_suffix',
            'diagnoses.icd10Code:id,code,name_en,name_id',
        ];
    }

    private function visitRelations(): array
    {
        return [
            'patient:id,full_name,phone',
            'patientBranchRecord:id,patient_id,branch_id,medical_record_no',
            'branch:id,name,code',
            'section:id,name,code,type',
            'doctor:id,full_name,title_prefix,title_suffix',
            'latestVitalSign',
            'medicalRecord' => fn ($query) => $query->with([
                'doctor:id,full_name,title_prefix,title_suffix',
                'diagnoses.icd10Code:id,code,name_en,name_id',
            ]),
        ];
    }

    private function workflowVisitRelations(): array
    {
        return [
            'medicalRecord',
            'prescription.items.dispenses',
            'visitMedicalServices',
            'visitProcedures',
            'laboratoryOrders',
            'invoice',
        ];
    }

    private function billingVisitRelations(): array
    {
        return [
            'medicalRecord.doctor',
            'prescription.items.dispenses',
            'visitMedicalServices.medicalService',
            'visitProcedures.procedureMaster',
            'laboratoryOrders.laboratoryTest',
            'invoice.items',
        ];
    }

    private function isAdmin(User $actor): bool
    {
        return $actor->hasAnyRole(['super-admin', 'clinic-admin']);
    }
}
