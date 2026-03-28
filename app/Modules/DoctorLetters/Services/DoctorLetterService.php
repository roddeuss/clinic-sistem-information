<?php

namespace App\Modules\DoctorLetters\Services;

use App\Models\DoctorLetter;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Modules\DoctorLetters\Exceptions\DoctorLetterManagementException;
use App\Services\AuditLogService;
use App\Services\BranchDocumentNumberService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorLetterService
{
    private const ALLOWED_SORTS = [
        'letter_no',
        'letter_type',
        'status',
        'issued_at',
        'created_at',
    ];

    public function __construct(
        private readonly BranchDocumentNumberService $branchDocumentNumberService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);

        return [
            'filters' => $filters,
            'summary' => $this->summary(),
            'letters' => $this->table($filters),
            'visitOptions' => $this->visitOptions(),
            'letterTypeOptions' => $this->letterTypeOptions(),
            'abilities' => $this->abilities(),
            'sortOptions' => [
                'issued_at' => 'Waktu issue',
                'created_at' => 'Waktu dibuat',
                'status' => 'Status',
                'letter_type' => 'Jenis surat',
                'letter_no' => 'Nomor surat',
            ],
            'perPageOptions' => [10, 25, 50, 100],
        ];
    }

    public function createLetter(array $payload, User $actor): array
    {
        return $this->transactional(function () use ($payload, $actor): array {
            $visit = $this->resolveManagedVisit((int) $payload['visit_registration_id'], true);
            $this->validateTypePayload($payload);
            $attributes = $this->letterAttributes($visit, $payload);

            $existingDrafts = DoctorLetter::query()
                ->where('visit_registration_id', $visit->id)
                ->where('letter_type', $attributes['letter_type'])
                ->where('status', 'draft')
                ->lockForUpdate()
                ->get();

            foreach ($existingDrafts as $existingDraft) {
                if ($this->letterMatchesDesiredState($existingDraft, $attributes)) {
                    return ['doctorLetter' => $existingDraft->fresh($this->relations()), 'changed' => false];
                }
            }

            if ($existingDrafts->isNotEmpty()) {
                throw new DoctorLetterManagementException(
                    'Masih ada draft surat dengan tipe yang sama untuk visit ini. Perbarui draft yang sudah ada.',
                    409,
                    'letter_type',
                );
            }

            $doctorLetter = DoctorLetter::query()->create($attributes);
            $doctorLetter = $doctorLetter->fresh($this->relations());

            $this->auditLogService->log(
                module: 'doctor_letter_management',
                action: 'create_draft',
                auditable: $doctorLetter,
                description: sprintf('Draft surat dokter %s dibuat oleh %s.', $doctorLetter->getKey(), $actor->email),
                after: $this->letterAuditSnapshot($doctorLetter),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $doctorLetter->visit_registration_id,
                    'branch_id' => $doctorLetter->branch_id,
                ],
            );

            return ['doctorLetter' => $doctorLetter, 'changed' => true];
        });
    }

    public function updateLetter(DoctorLetter $doctorLetter, array $payload, User $actor): array
    {
        return $this->transactional(function () use ($doctorLetter, $payload, $actor): array {
            $lockedLetter = $this->lockLetter($doctorLetter->getKey());
            $this->ensureDraft($lockedLetter);

            $visit = $this->resolveManagedVisit((int) $payload['visit_registration_id'], true);
            $this->validateTypePayload($payload);
            $attributes = $this->letterAttributes($visit, $payload);

            if ((int) $lockedLetter->visit_registration_id !== (int) $attributes['visit_registration_id']
                || $lockedLetter->letter_type !== $attributes['letter_type']) {
                $duplicateDraft = DoctorLetter::query()
                    ->where('visit_registration_id', $attributes['visit_registration_id'])
                    ->where('letter_type', $attributes['letter_type'])
                    ->where('status', 'draft')
                    ->whereKeyNot($lockedLetter->getKey())
                    ->lockForUpdate()
                    ->exists();

                if ($duplicateDraft) {
                    throw new DoctorLetterManagementException(
                        'Masih ada draft surat dengan tipe yang sama untuk visit ini. Perbarui draft yang sudah ada.',
                        409,
                        'letter_type',
                    );
                }
            }

            if ($this->letterMatchesDesiredState($lockedLetter, $attributes)) {
                return ['doctorLetter' => $lockedLetter, 'changed' => false];
            }

            $before = $this->letterAuditSnapshot($lockedLetter);

            $lockedLetter->fill($attributes);
            $lockedLetter->save();
            $lockedLetter = $lockedLetter->fresh($this->relations());

            $this->auditLogService->log(
                module: 'doctor_letter_management',
                action: 'update_draft',
                auditable: $lockedLetter,
                description: sprintf('Draft surat dokter %s diperbarui oleh %s.', $lockedLetter->getKey(), $actor->email),
                before: $before,
                after: $this->letterAuditSnapshot($lockedLetter),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $lockedLetter->visit_registration_id,
                    'branch_id' => $lockedLetter->branch_id,
                ],
            );

            return ['doctorLetter' => $lockedLetter, 'changed' => true];
        });
    }

    public function issueLetter(DoctorLetter $doctorLetter, User $actor): array
    {
        return $this->transactional(function () use ($doctorLetter, $actor): array {
            $lockedLetter = $this->lockLetter($doctorLetter->getKey());

            if ($lockedLetter->isIssued()) {
                return ['doctorLetter' => $lockedLetter, 'changed' => false];
            }

            $this->ensureDraft($lockedLetter);

            $visit = $this->resolveManagedVisit((int) $lockedLetter->visit_registration_id, true);
            $medicalRecord = $visit->medicalRecord;

            if (! $medicalRecord || $medicalRecord->status !== 'final') {
                throw new DoctorLetterManagementException(
                    'Surat dokter hanya bisa di-issue untuk visit dengan SOAP final.',
                    422,
                    'doctor_letter',
                );
            }

            $doctor = $medicalRecord->doctor ?: $visit->doctor;

            if (! $doctor) {
                throw new DoctorLetterManagementException(
                    'Data dokter untuk surat ini belum lengkap.',
                    409,
                    'doctor_letter',
                );
            }

            $before = $this->letterAuditSnapshot($lockedLetter);

            $lockedLetter->update([
                'doctor_id' => $doctor->id,
                'letter_no' => $lockedLetter->letter_no ?: $this->branchDocumentNumberService->nextDoctorLetterNumber($visit->branch, $lockedLetter->letter_type),
                'status' => 'issued',
                'issue_date' => $lockedLetter->issue_date ?: now()->toDateString(),
                'doctor_name_snapshot' => $doctor->displayName(),
                'doctor_specialization_snapshot' => $doctor->specialization,
                'doctor_signature_path_snapshot' => $doctor->signature_path,
                'sip_number_snapshot' => $doctor->sip_number,
                'issued_by_user_id' => $actor->getKey(),
                'issued_at' => $lockedLetter->issued_at ?? now(),
                'voided_at' => null,
                'voided_by_user_id' => null,
                'void_reason' => null,
            ]);
            $lockedLetter = $lockedLetter->fresh($this->relations());

            $this->auditLogService->log(
                module: 'doctor_letter_management',
                action: 'issue_letter',
                auditable: $lockedLetter,
                description: sprintf('Surat dokter %s di-issue oleh %s.', $lockedLetter->letter_no, $actor->email),
                before: $before,
                after: $this->letterAuditSnapshot($lockedLetter),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $lockedLetter->visit_registration_id,
                    'branch_id' => $lockedLetter->branch_id,
                ],
            );

            return ['doctorLetter' => $lockedLetter, 'changed' => true];
        });
    }

    public function printLetter(DoctorLetter $doctorLetter, User $actor): array
    {
        return $this->transactional(function () use ($doctorLetter, $actor): array {
            $lockedLetter = $this->lockLetter($doctorLetter->getKey());

            if (! $lockedLetter->isIssued()) {
                throw new DoctorLetterManagementException(
                    'Hanya surat issued yang bisa dicetak.',
                    409,
                    'doctor_letter',
                );
            }

            if ($lockedLetter->printed_at !== null) {
                return ['doctorLetter' => $lockedLetter->fresh($this->relations()), 'changed' => false];
            }

            $before = $this->letterAuditSnapshot($lockedLetter);

            $lockedLetter->update(['printed_at' => now()]);
            $lockedLetter = $lockedLetter->fresh($this->relations());

            $this->auditLogService->log(
                module: 'doctor_letter_management',
                action: 'print_letter',
                auditable: $lockedLetter,
                description: sprintf('Surat dokter %s dibuka untuk cetak oleh %s.', $lockedLetter->letter_no, $actor->email),
                before: $before,
                after: $this->letterAuditSnapshot($lockedLetter),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $lockedLetter->visit_registration_id,
                    'branch_id' => $lockedLetter->branch_id,
                ],
            );

            return ['doctorLetter' => $lockedLetter, 'changed' => true];
        });
    }

    public function voidLetter(DoctorLetter $doctorLetter, array $payload, User $actor): array
    {
        return $this->transactional(function () use ($doctorLetter, $payload, $actor): array {
            $lockedLetter = $this->lockLetter($doctorLetter->getKey());

            if ($lockedLetter->isVoided()) {
                if ($lockedLetter->void_reason === $payload['void_reason']) {
                    return ['doctorLetter' => $lockedLetter, 'changed' => false];
                }

                throw new DoctorLetterManagementException(
                    'Surat dokter ini sudah di-void sebelumnya dengan alasan yang berbeda.',
                    409,
                    'doctor_letter',
                );
            }

            if (! $lockedLetter->isIssued()) {
                throw new DoctorLetterManagementException(
                    'Hanya surat issued yang bisa di-void.',
                    409,
                    'doctor_letter',
                );
            }

            $before = $this->letterAuditSnapshot($lockedLetter);

            $lockedLetter->update([
                'status' => 'voided',
                'void_reason' => $payload['void_reason'],
                'voided_at' => $lockedLetter->voided_at ?? now(),
                'voided_by_user_id' => $actor->getKey(),
            ]);
            $lockedLetter = $lockedLetter->fresh($this->relations());

            $this->auditLogService->log(
                module: 'doctor_letter_management',
                action: 'void_letter',
                auditable: $lockedLetter,
                description: sprintf('Surat dokter %s di-void oleh %s.', $lockedLetter->letter_no, $actor->email),
                before: $before,
                after: $this->letterAuditSnapshot($lockedLetter),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $lockedLetter->visit_registration_id,
                    'branch_id' => $lockedLetter->branch_id,
                ],
            );

            return ['doctorLetter' => $lockedLetter, 'changed' => true];
        });
    }

    public function reissueLetter(DoctorLetter $doctorLetter, User $actor): array
    {
        return $this->transactional(function () use ($doctorLetter, $actor): array {
            $lockedLetter = $this->lockLetter($doctorLetter->getKey());

            if (! in_array($lockedLetter->status, ['issued', 'voided'], true)) {
                throw new DoctorLetterManagementException(
                    'Hanya surat issued atau voided yang bisa direissue.',
                    409,
                    'doctor_letter',
                );
            }

            $visit = $this->resolveManagedVisit((int) $lockedLetter->visit_registration_id, true);

            $newLetter = DoctorLetter::query()->create([
                'visit_registration_id' => $lockedLetter->visit_registration_id,
                'patient_id' => $lockedLetter->patient_id,
                'patient_branch_record_id' => $lockedLetter->patient_branch_record_id,
                'branch_id' => $lockedLetter->branch_id,
                'section_id' => $lockedLetter->section_id,
                'doctor_id' => $lockedLetter->doctor_id,
                'reissued_from_id' => $lockedLetter->id,
                'issued_by_user_id' => $actor->getKey(),
                'letter_no' => $this->branchDocumentNumberService->nextDoctorLetterNumber($visit->branch, $lockedLetter->letter_type),
                'letter_type' => $lockedLetter->letter_type,
                'status' => 'issued',
                'issue_date' => now()->toDateString(),
                'diagnosis_summary' => $lockedLetter->diagnosis_summary,
                'notes' => $lockedLetter->notes,
                'sick_start_date' => $lockedLetter->sick_start_date,
                'sick_end_date' => $lockedLetter->sick_end_date,
                'sick_total_days' => $lockedLetter->sick_total_days,
                'healthy_statement' => $lockedLetter->healthy_statement,
                'control_date' => $lockedLetter->control_date,
                'control_notes' => $lockedLetter->control_notes,
                'drug_test_date' => $lockedLetter->drug_test_date,
                'drug_test_method' => $lockedLetter->drug_test_method,
                'drug_test_result' => $lockedLetter->drug_test_result,
                'drug_free_statement' => $lockedLetter->drug_free_statement,
                'doctor_name_snapshot' => $lockedLetter->doctor_name_snapshot,
                'doctor_specialization_snapshot' => $lockedLetter->doctor_specialization_snapshot,
                'doctor_signature_path_snapshot' => $lockedLetter->doctor_signature_path_snapshot,
                'sip_number_snapshot' => $lockedLetter->sip_number_snapshot,
                'issued_at' => now(),
            ]);
            $newLetter = $newLetter->fresh($this->relations());

            $this->auditLogService->log(
                module: 'doctor_letter_management',
                action: 'reissue_letter',
                auditable: $newLetter,
                description: sprintf('Surat dokter %s direissue dari surat %s oleh %s.', $newLetter->letter_no, $lockedLetter->letter_no, $actor->email),
                after: $this->letterAuditSnapshot($newLetter),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $newLetter->visit_registration_id,
                    'branch_id' => $newLetter->branch_id,
                    'source_letter_id' => $lockedLetter->getKey(),
                ],
            );

            return ['doctorLetter' => $newLetter, 'changed' => true];
        });
    }

    public function deleteLetter(DoctorLetter $doctorLetter, User $actor): array
    {
        return $this->transactional(function () use ($doctorLetter, $actor): array {
            $lockedLetter = $this->lockLetter($doctorLetter->getKey());
            $this->ensureDraft($lockedLetter);

            $before = $this->letterAuditSnapshot($lockedLetter);
            $lockedLetter->delete();

            $this->auditLogService->log(
                module: 'doctor_letter_management',
                action: 'delete_draft',
                auditable: $lockedLetter,
                description: sprintf('Draft surat dokter %s dihapus oleh %s.', $lockedLetter->getKey(), $actor->email),
                before: $before,
                after: [],
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $lockedLetter->visit_registration_id,
                    'branch_id' => $lockedLetter->branch_id,
                ],
            );

            return ['doctorLetter' => $lockedLetter, 'changed' => true];
        });
    }

    public function letterPayload(DoctorLetter $doctorLetter): array
    {
        $doctorLetter->loadMissing($this->relations());

        return [
            'id' => $doctorLetter->getKey(),
            'visit_registration_id' => $doctorLetter->visit_registration_id,
            'letter_no' => $doctorLetter->letter_no,
            'letter_type' => $doctorLetter->letter_type,
            'status' => $doctorLetter->status,
            'issue_date' => $doctorLetter->issue_date?->toDateString(),
            'patient_name' => $doctorLetter->patient?->full_name,
            'medical_record_no' => $doctorLetter->patientBranchRecord?->medical_record_no,
            'doctor_name' => $doctorLetter->doctor_name_snapshot ?: $doctorLetter->doctor?->displayName(),
            'diagnosis_summary' => $doctorLetter->diagnosis_summary,
            'notes' => $doctorLetter->notes,
            'sick_start_date' => $doctorLetter->sick_start_date?->toDateString(),
            'sick_end_date' => $doctorLetter->sick_end_date?->toDateString(),
            'sick_total_days' => $doctorLetter->sick_total_days,
            'healthy_statement' => $doctorLetter->healthy_statement,
            'control_date' => $doctorLetter->control_date?->toDateString(),
            'control_notes' => $doctorLetter->control_notes,
            'drug_test_date' => $doctorLetter->drug_test_date?->toDateString(),
            'drug_test_method' => $doctorLetter->drug_test_method,
            'drug_test_result' => $doctorLetter->drug_test_result,
            'drug_free_statement' => $doctorLetter->drug_free_statement,
            'void_reason' => $doctorLetter->void_reason,
            'issued_at' => $doctorLetter->issued_at?->toIso8601String(),
            'voided_at' => $doctorLetter->voided_at?->toIso8601String(),
            'printed_at' => $doctorLetter->printed_at?->toIso8601String(),
        ];
    }

    private function transactional(callable $callback): mixed
    {
        try {
            return DB::transaction($callback);
        } catch (DoctorLetterManagementException $exception) {
            throw $exception;
        } catch (ValidationException $exception) {
            throw $this->mapValidationException($exception);
        }
    }

    private function mapValidationException(ValidationException $exception): DoctorLetterManagementException
    {
        $errors = $exception->errors();
        $key = (string) (array_key_first($errors) ?? 'doctor_letter');
        $message = (string) ($errors[$key][0] ?? $exception->getMessage());

        return new DoctorLetterManagementException($message, 422, $key);
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'search' => trim((string) ($filters['search'] ?? '')),
            'status' => (string) ($filters['status'] ?? ''),
            'letter_type' => (string) ($filters['letter_type'] ?? ''),
            'sort_by' => (string) ($filters['sort_by'] ?? 'issued_at'),
            'sort_direction' => (string) ($filters['sort_direction'] ?? 'desc'),
            'per_page' => (int) ($filters['per_page'] ?? 10),
        ];
    }

    private function summary(): array
    {
        $baseQuery = DoctorLetter::query();

        return [
            'total' => (clone $baseQuery)->count(),
            'draft' => (clone $baseQuery)->where('status', 'draft')->count(),
            'issued' => (clone $baseQuery)->where('status', 'issued')->count(),
            'voided' => (clone $baseQuery)->where('status', 'voided')->count(),
        ];
    }

    private function table(array $filters): LengthAwarePaginator
    {
        return DoctorLetter::query()
            ->with($this->relations())
            ->searchForManagement($filters['search'])
            ->filterStatus($filters['status'])
            ->filterLetterType($filters['letter_type'])
            ->orderByAllowed($filters['sort_by'], $filters['sort_direction'], self::ALLOWED_SORTS)
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    private function visitOptions(): Collection
    {
        return VisitRegistration::query()
            ->with([
                'patient:id,full_name,phone',
                'patientBranchRecord:id,medical_record_no',
                'branch:id,name,code',
                'medicalRecord:id,visit_registration_id,doctor_id,status,assessment',
                'medicalRecord.diagnoses.icd10Code:id,code,name_en',
            ])
            ->whereHas('medicalRecord')
            ->whereNot('care_stage', 'cancelled')
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->limit(150)
            ->get([
                'id',
                'patient_id',
                'patient_branch_record_id',
                'branch_id',
                'section_id',
                'doctor_id',
                'visit_date',
            ]);
    }

    private function abilities(): array
    {
        $user = auth()->user();

        return [
            'create' => $user?->hasRole('super-admin') || ($user?->can('create doctor letter management') ?? false),
            'edit' => $user?->hasRole('super-admin') || ($user?->can('edit doctor letter management') ?? false),
            'delete' => $user?->hasRole('super-admin') || ($user?->can('delete doctor letter management') ?? false),
            'issue' => $user?->hasRole('super-admin') || ($user?->can('issue doctor letter management') ?? false),
            'print' => $user?->hasRole('super-admin') || ($user?->can('print doctor letter management') ?? false),
        ];
    }

    private function resolveManagedVisit(int $visitRegistrationId, bool $lock = false): VisitRegistration
    {
        $query = VisitRegistration::query()->with([
            'patient',
            'patientBranchRecord',
            'branch',
            'section',
            'doctor',
            'medicalRecord.diagnoses.icd10Code',
            'medicalRecord.doctor',
        ]);

        if ($lock) {
            $query->lockForUpdate();
        }

        $visit = $query->find($visitRegistrationId);

        if ($visit === null) {
            throw new DoctorLetterManagementException('Visit registration tidak ditemukan.', 404, 'visit_registration_id');
        }

        if (! $visit->medicalRecord) {
            throw new DoctorLetterManagementException(
                'Surat dokter hanya bisa dibuat untuk visit yang sudah punya medical record.',
                422,
                'visit_registration_id',
            );
        }

        if ($visit->care_stage === 'cancelled') {
            throw new DoctorLetterManagementException(
                'Visit yang dibatalkan tidak bisa dipakai untuk surat dokter.',
                409,
                'visit_registration_id',
            );
        }

        return $visit;
    }

    private function lockLetter(int $doctorLetterId): DoctorLetter
    {
        $doctorLetter = DoctorLetter::query()
            ->with($this->relations())
            ->lockForUpdate()
            ->find($doctorLetterId);

        if ($doctorLetter === null) {
            throw new DoctorLetterManagementException('Surat dokter tidak ditemukan.', 404, 'doctor_letter');
        }

        return $doctorLetter;
    }

    private function letterTypeOptions(): array
    {
        return [
            'sick_note' => 'Surat Sakit',
            'fit_note' => 'Surat Sehat',
            'control_note' => 'Surat Kontrol',
            'drug_free_note' => 'Surat Bebas Narkoba',
        ];
    }

    private function letterAttributes(VisitRegistration $visit, array $payload): array
    {
        $sickStartDate = $payload['sick_start_date'] ? Carbon::parse($payload['sick_start_date'])->toDateString() : null;
        $sickEndDate = $payload['sick_end_date'] ? Carbon::parse($payload['sick_end_date'])->toDateString() : null;

        return [
            'visit_registration_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'patient_branch_record_id' => $visit->patient_branch_record_id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'doctor_id' => $visit->medicalRecord?->doctor_id ?: $visit->doctor_id,
            'letter_type' => $payload['letter_type'],
            'status' => 'draft',
            'issue_date' => $payload['issue_date'] ?: now()->toDateString(),
            'diagnosis_summary' => $payload['diagnosis_summary'] ?: $this->defaultDiagnosisSummary($visit),
            'notes' => $payload['notes'] ?? null,
            'sick_start_date' => $payload['letter_type'] === 'sick_note' ? $sickStartDate : null,
            'sick_end_date' => $payload['letter_type'] === 'sick_note' ? $sickEndDate : null,
            'sick_total_days' => $payload['letter_type'] === 'sick_note' ? $this->calculateSickDays($sickStartDate, $sickEndDate) : null,
            'healthy_statement' => $payload['letter_type'] === 'fit_note'
                ? ($payload['healthy_statement'] ?: 'Berdasarkan pemeriksaan dokter, pasien dinyatakan dalam kondisi sehat untuk aktivitas sesuai penilaian klinis.')
                : null,
            'control_date' => $payload['letter_type'] === 'control_note' && $payload['control_date']
                ? Carbon::parse($payload['control_date'])->toDateString()
                : null,
            'control_notes' => $payload['letter_type'] === 'control_note' ? ($payload['control_notes'] ?? null) : null,
            'drug_test_date' => $payload['letter_type'] === 'drug_free_note' && $payload['drug_test_date']
                ? Carbon::parse($payload['drug_test_date'])->toDateString()
                : null,
            'drug_test_method' => $payload['letter_type'] === 'drug_free_note'
                ? ($payload['drug_test_method'] ?: 'Rapid test urine')
                : null,
            'drug_test_result' => $payload['letter_type'] === 'drug_free_note'
                ? ($payload['drug_test_result'] ?: 'Negatif')
                : null,
            'drug_free_statement' => $payload['letter_type'] === 'drug_free_note'
                ? ($payload['drug_free_statement'] ?: 'Berdasarkan pemeriksaan klinis dan hasil pemeriksaan penunjang yang tersedia, pasien dinyatakan bebas dari indikasi penggunaan narkotika, psikotropika, dan zat adiktif lainnya pada saat surat ini diterbitkan.')
                : null,
        ];
    }

    private function validateTypePayload(array $payload): void
    {
        if ($payload['letter_type'] === 'sick_note' && (! $payload['sick_start_date'] || ! $payload['sick_end_date'])) {
            throw new DoctorLetterManagementException(
                'Surat sakit wajib memiliki tanggal mulai dan tanggal selesai.',
                422,
                'sick_start_date',
            );
        }

        if ($payload['letter_type'] === 'control_note' && ! $payload['control_date']) {
            throw new DoctorLetterManagementException(
                'Surat kontrol wajib memiliki tanggal kontrol berikutnya.',
                422,
                'control_date',
            );
        }

        if ($payload['letter_type'] === 'drug_free_note' && ! $payload['drug_test_date']) {
            throw new DoctorLetterManagementException(
                'Surat bebas narkoba wajib memiliki tanggal pemeriksaan atau screening.',
                422,
                'drug_test_date',
            );
        }
    }

    private function ensureDraft(DoctorLetter $doctorLetter): void
    {
        if (! $doctorLetter->isDraft()) {
            throw new DoctorLetterManagementException(
                'Hanya draft surat yang bisa diubah atau dihapus.',
                409,
                'doctor_letter',
            );
        }
    }

    private function calculateSickDays(?string $startDate, ?string $endDate): ?int
    {
        if (! $startDate || ! $endDate) {
            return null;
        }

        return Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
    }

    private function defaultDiagnosisSummary(VisitRegistration $visit): ?string
    {
        return $visit->medicalRecord?->diagnoses
            ?->map(function ($diagnosis): ?string {
                $code = $diagnosis->icd10Code?->code;
                $label = $diagnosis->icd10Code?->name_en;

                if (! $code && ! $label) {
                    return null;
                }

                return trim(collect([$code, $label])->filter()->implode(' - '));
            })
            ->filter()
            ->implode('; ');
    }

    private function letterMatchesDesiredState(DoctorLetter $doctorLetter, array $attributes): bool
    {
        return (int) $doctorLetter->visit_registration_id === (int) $attributes['visit_registration_id']
            && (int) $doctorLetter->patient_id === (int) $attributes['patient_id']
            && (int) ($doctorLetter->patient_branch_record_id ?? 0) === (int) ($attributes['patient_branch_record_id'] ?? 0)
            && (int) $doctorLetter->branch_id === (int) $attributes['branch_id']
            && (int) ($doctorLetter->section_id ?? 0) === (int) ($attributes['section_id'] ?? 0)
            && (int) ($doctorLetter->doctor_id ?? 0) === (int) ($attributes['doctor_id'] ?? 0)
            && $doctorLetter->letter_type === $attributes['letter_type']
            && $doctorLetter->status === $attributes['status']
            && optional($doctorLetter->issue_date)->toDateString() === $attributes['issue_date']
            && $doctorLetter->diagnosis_summary === $attributes['diagnosis_summary']
            && $doctorLetter->notes === $attributes['notes']
            && optional($doctorLetter->sick_start_date)->toDateString() === $attributes['sick_start_date']
            && optional($doctorLetter->sick_end_date)->toDateString() === $attributes['sick_end_date']
            && (int) ($doctorLetter->sick_total_days ?? 0) === (int) ($attributes['sick_total_days'] ?? 0)
            && $doctorLetter->healthy_statement === $attributes['healthy_statement']
            && optional($doctorLetter->control_date)->toDateString() === $attributes['control_date']
            && $doctorLetter->control_notes === $attributes['control_notes']
            && optional($doctorLetter->drug_test_date)->toDateString() === $attributes['drug_test_date']
            && $doctorLetter->drug_test_method === $attributes['drug_test_method']
            && $doctorLetter->drug_test_result === $attributes['drug_test_result']
            && $doctorLetter->drug_free_statement === $attributes['drug_free_statement'];
    }

    private function letterAuditSnapshot(DoctorLetter $doctorLetter): array
    {
        $doctorLetter->loadMissing($this->relations());

        return [
            'id' => $doctorLetter->getKey(),
            'visit_registration_id' => $doctorLetter->visit_registration_id,
            'patient_id' => $doctorLetter->patient_id,
            'patient_branch_record_id' => $doctorLetter->patient_branch_record_id,
            'branch_id' => $doctorLetter->branch_id,
            'section_id' => $doctorLetter->section_id,
            'doctor_id' => $doctorLetter->doctor_id,
            'letter_no' => $doctorLetter->letter_no,
            'letter_type' => $doctorLetter->letter_type,
            'status' => $doctorLetter->status,
            'issue_date' => $doctorLetter->issue_date?->toDateString(),
            'diagnosis_summary' => $doctorLetter->diagnosis_summary,
            'notes' => $doctorLetter->notes,
            'sick_start_date' => $doctorLetter->sick_start_date?->toDateString(),
            'sick_end_date' => $doctorLetter->sick_end_date?->toDateString(),
            'sick_total_days' => $doctorLetter->sick_total_days,
            'healthy_statement' => $doctorLetter->healthy_statement,
            'control_date' => $doctorLetter->control_date?->toDateString(),
            'control_notes' => $doctorLetter->control_notes,
            'drug_test_date' => $doctorLetter->drug_test_date?->toDateString(),
            'drug_test_method' => $doctorLetter->drug_test_method,
            'drug_test_result' => $doctorLetter->drug_test_result,
            'drug_free_statement' => $doctorLetter->drug_free_statement,
            'doctor_name_snapshot' => $doctorLetter->doctor_name_snapshot,
            'doctor_specialization_snapshot' => $doctorLetter->doctor_specialization_snapshot,
            'doctor_signature_path_snapshot' => $doctorLetter->doctor_signature_path_snapshot,
            'sip_number_snapshot' => $doctorLetter->sip_number_snapshot,
            'issued_at' => $doctorLetter->issued_at?->toIso8601String(),
            'voided_at' => $doctorLetter->voided_at?->toIso8601String(),
            'printed_at' => $doctorLetter->printed_at?->toIso8601String(),
            'void_reason' => $doctorLetter->void_reason,
        ];
    }

    private function relations(): array
    {
        return [
            'patient:id,full_name,gender,date_of_birth,phone',
            'patientBranchRecord:id,medical_record_no',
            'branch:id,clinic_id,name,code,address,phone',
            'branch.clinic:id,name,address,phone',
            'section:id,name,code',
            'doctor:id,full_name,title_prefix,title_suffix,specialization,sip_number,signature_path',
            'visitRegistration.medicalRecord:id,visit_registration_id,status,doctor_id',
            'issuedBy:id,name',
            'voidedBy:id,name',
            'reissuedFrom:id,letter_no',
        ];
    }
}
