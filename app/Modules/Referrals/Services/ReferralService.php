<?php

namespace App\Modules\Referrals\Services;

use App\Models\PatientReferral;
use App\Models\ReferralDestination;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Modules\Referrals\Exceptions\ReferralManagementException;
use App\Services\AuditLogService;
use App\Services\BranchDocumentNumberService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReferralService
{
    private const DESTINATION_ALLOWED_SORTS = [
        'code',
        'name',
        'destination_type',
        'created_at',
    ];

    private const REFERRAL_ALLOWED_SORTS = [
        'referral_no',
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
            'destinationTypeOptions' => [
                'hospital' => 'Hospital',
                'specialist' => 'Specialist',
                'lab_radiology' => 'Lab / Radiology',
            ],
            'summary' => $this->summary(),
            'destinations' => $this->destinationTable($filters),
            'referrals' => $this->referralTable($filters),
            'visitOptions' => $this->visitOptions(),
            'destinationOptions' => ReferralDestination::query()
                ->where('is_active', true)
                ->orderBy('destination_type')
                ->orderBy('name')
                ->get(['id', 'code', 'destination_type', 'name', 'phone']),
            'abilities' => $this->abilities(),
            'destinationSortOptions' => [
                'name' => 'Nama tujuan',
                'code' => 'Kode tujuan',
                'destination_type' => 'Tipe tujuan',
                'created_at' => 'Waktu dibuat',
            ],
            'referralSortOptions' => [
                'issued_at' => 'Waktu issue',
                'created_at' => 'Waktu dibuat',
                'status' => 'Status',
                'referral_no' => 'Nomor referral',
            ],
            'perPageOptions' => [10, 25, 50, 100],
        ];
    }

    public function createDestination(array $payload, User $actor): array
    {
        return $this->transactional(function () use ($payload, $actor): array {
            $existing = ReferralDestination::query()
                ->where('code', strtoupper(trim($payload['code'])))
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($this->destinationMatchesDesiredState($existing, $payload)) {
                    return ['destination' => $existing, 'changed' => false];
                }

                throw new ReferralManagementException(
                    'Kode tujuan referral sudah dipakai oleh data lain.',
                    409,
                    'code',
                );
            }

            $destination = ReferralDestination::query()->create($this->destinationAttributes($payload));

            $this->auditLogService->log(
                module: 'referral_management',
                action: 'create_destination',
                auditable: $destination,
                description: sprintf('Tujuan referral %s dibuat oleh %s.', $destination->code, $actor->email),
                after: $this->destinationAuditSnapshot($destination),
                meta: ['actor_user_id' => $actor->getKey()],
            );

            return ['destination' => $destination, 'changed' => true];
        });
    }

    public function updateDestination(ReferralDestination $destination, array $payload, User $actor): array
    {
        return $this->transactional(function () use ($destination, $payload, $actor): array {
            $lockedDestination = $this->lockDestination($destination->getKey());
            $normalizedCode = strtoupper(trim($payload['code']));

            if ($normalizedCode !== $lockedDestination->code) {
                $duplicateExists = ReferralDestination::query()
                    ->where('code', $normalizedCode)
                    ->whereKeyNot($lockedDestination->getKey())
                    ->lockForUpdate()
                    ->exists();

                if ($duplicateExists) {
                    throw new ReferralManagementException(
                        'Kode tujuan referral sudah dipakai oleh data lain.',
                        409,
                        'code',
                    );
                }
            }

            if ($this->destinationMatchesDesiredState($lockedDestination, $payload)) {
                return ['destination' => $lockedDestination, 'changed' => false];
            }

            $before = $this->destinationAuditSnapshot($lockedDestination);

            $lockedDestination->fill($this->destinationAttributes($payload));
            $lockedDestination->save();
            $lockedDestination = $lockedDestination->fresh();

            $this->auditLogService->log(
                module: 'referral_management',
                action: 'update_destination',
                auditable: $lockedDestination,
                description: sprintf('Tujuan referral %s diperbarui oleh %s.', $lockedDestination->code, $actor->email),
                before: $before,
                after: $this->destinationAuditSnapshot($lockedDestination),
                meta: ['actor_user_id' => $actor->getKey()],
            );

            return ['destination' => $lockedDestination, 'changed' => true];
        });
    }

    public function archiveDestination(ReferralDestination $destination, User $actor): array
    {
        return $this->transactional(function () use ($destination, $actor): array {
            $lockedDestination = $this->lockDestination($destination->getKey());

            if (! $lockedDestination->is_active) {
                return ['destination' => $lockedDestination, 'changed' => false];
            }

            $before = $this->destinationAuditSnapshot($lockedDestination);

            $lockedDestination->update(['is_active' => false]);
            $lockedDestination = $lockedDestination->fresh();

            $this->auditLogService->log(
                module: 'referral_management',
                action: 'archive_destination',
                auditable: $lockedDestination,
                description: sprintf('Tujuan referral %s diarsipkan oleh %s.', $lockedDestination->code, $actor->email),
                before: $before,
                after: $this->destinationAuditSnapshot($lockedDestination),
                meta: ['actor_user_id' => $actor->getKey()],
            );

            return ['destination' => $lockedDestination, 'changed' => true];
        });
    }

    public function createReferral(array $payload, User $actor): array
    {
        return $this->transactional(function () use ($payload, $actor): array {
            $visit = $this->resolveManagedVisit((int) $payload['visit_registration_id'], true);
            $destination = $this->resolveManagedDestination((int) $payload['referral_destination_id'], true);

            $referral = PatientReferral::query()->create(
                $this->referralAttributes($visit, $destination, $payload)
            );
            $referral = $referral->fresh($this->referralRelations());

            $this->auditLogService->log(
                module: 'referral_management',
                action: 'create_referral_draft',
                auditable: $referral,
                description: sprintf('Draft referral %s dibuat oleh %s.', $referral->getKey(), $actor->email),
                after: $this->referralAuditSnapshot($referral),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $referral->visit_registration_id,
                    'branch_id' => $referral->branch_id,
                ],
            );

            return ['referral' => $referral, 'changed' => true];
        });
    }

    public function updateReferral(PatientReferral $referral, array $payload, User $actor): array
    {
        return $this->transactional(function () use ($referral, $payload, $actor): array {
            $lockedReferral = $this->lockReferral($referral->getKey());
            $this->ensureDraft($lockedReferral);

            $visit = $this->resolveManagedVisit((int) $payload['visit_registration_id'], true);
            $destination = $this->resolveManagedDestination((int) $payload['referral_destination_id'], true);

            if ($this->referralMatchesDesiredState($lockedReferral, $visit, $destination, $payload)) {
                return ['referral' => $lockedReferral, 'changed' => false];
            }

            $before = $this->referralAuditSnapshot($lockedReferral);

            $lockedReferral->fill($this->referralAttributes($visit, $destination, $payload));
            $lockedReferral->save();
            $lockedReferral = $lockedReferral->fresh($this->referralRelations());

            $this->auditLogService->log(
                module: 'referral_management',
                action: 'update_referral_draft',
                auditable: $lockedReferral,
                description: sprintf('Draft referral %s diperbarui oleh %s.', $lockedReferral->getKey(), $actor->email),
                before: $before,
                after: $this->referralAuditSnapshot($lockedReferral),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $lockedReferral->visit_registration_id,
                    'branch_id' => $lockedReferral->branch_id,
                ],
            );

            return ['referral' => $lockedReferral, 'changed' => true];
        });
    }

    public function issueReferral(PatientReferral $referral, User $actor): array
    {
        return $this->transactional(function () use ($referral, $actor): array {
            $lockedReferral = $this->lockReferral($referral->getKey());

            if ($lockedReferral->isIssued()) {
                return ['referral' => $lockedReferral, 'changed' => false];
            }

            $this->ensureDraft($lockedReferral);

            $visit = $this->resolveManagedVisit((int) $lockedReferral->visit_registration_id, true);
            $medicalRecord = $visit->medicalRecord;

            if (! $medicalRecord || $medicalRecord->status !== 'final') {
                throw new ReferralManagementException(
                    'Referral hanya bisa di-issue untuk visit dengan SOAP final.',
                    422,
                    'referral',
                );
            }

            $doctor = $medicalRecord->doctor ?: $visit->doctor;

            if (! $doctor) {
                throw new ReferralManagementException(
                    'Dokter visit tidak ditemukan untuk dokumen referral ini.',
                    409,
                    'referral',
                );
            }

            $destination = $lockedReferral->destination;

            if (! $destination) {
                throw new ReferralManagementException(
                    'Tujuan referral tidak ditemukan.',
                    404,
                    'referral_destination_id',
                );
            }

            $before = $this->referralAuditSnapshot($lockedReferral);

            $lockedReferral->update([
                'doctor_id' => $doctor->id,
                'referral_no' => $lockedReferral->referral_no ?: $this->branchDocumentNumberService->nextReferralNumber($visit->branch),
                'status' => 'issued',
                'destination_type' => $destination->destination_type,
                'destination_name' => $destination->name,
                'destination_address' => $destination->address,
                'destination_phone' => $destination->phone,
                'doctor_name_snapshot' => $doctor->displayName(),
                'doctor_specialization_snapshot' => $doctor->specialization,
                'doctor_signature_path_snapshot' => $doctor->signature_path,
                'issued_by_user_id' => $actor->getKey(),
                'issued_at' => $lockedReferral->issued_at ?? now(),
                'voided_at' => null,
                'voided_by_user_id' => null,
                'void_reason' => null,
            ]);
            $lockedReferral = $lockedReferral->fresh($this->printRelations());

            $this->auditLogService->log(
                module: 'referral_management',
                action: 'issue_referral',
                auditable: $lockedReferral,
                description: sprintf('Referral %s di-issue oleh %s.', $lockedReferral->referral_no, $actor->email),
                before: $before,
                after: $this->referralAuditSnapshot($lockedReferral),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $lockedReferral->visit_registration_id,
                    'branch_id' => $lockedReferral->branch_id,
                ],
            );

            return ['referral' => $lockedReferral, 'changed' => true];
        });
    }

    public function printReferral(PatientReferral $referral, User $actor): array
    {
        return $this->transactional(function () use ($referral, $actor): array {
            $lockedReferral = $this->lockReferral($referral->getKey());

            if (! $lockedReferral->isIssued()) {
                throw new ReferralManagementException(
                    'Hanya referral issued yang bisa dicetak.',
                    409,
                    'referral',
                );
            }

            if ($lockedReferral->printed_at !== null) {
                return ['referral' => $lockedReferral->fresh($this->printRelations()), 'changed' => false];
            }

            $before = $this->referralAuditSnapshot($lockedReferral);

            $lockedReferral->update(['printed_at' => now()]);
            $lockedReferral = $lockedReferral->fresh($this->printRelations());

            $this->auditLogService->log(
                module: 'referral_management',
                action: 'print_referral',
                auditable: $lockedReferral,
                description: sprintf('Referral %s dibuka untuk cetak oleh %s.', $lockedReferral->referral_no, $actor->email),
                before: $before,
                after: $this->referralAuditSnapshot($lockedReferral),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $lockedReferral->visit_registration_id,
                    'branch_id' => $lockedReferral->branch_id,
                ],
            );

            return ['referral' => $lockedReferral, 'changed' => true];
        });
    }

    public function voidReferral(PatientReferral $referral, array $payload, User $actor): array
    {
        return $this->transactional(function () use ($referral, $payload, $actor): array {
            $lockedReferral = $this->lockReferral($referral->getKey());

            if ($lockedReferral->isVoided()) {
                if ($lockedReferral->void_reason === $payload['void_reason']) {
                    return ['referral' => $lockedReferral, 'changed' => false];
                }

                throw new ReferralManagementException(
                    'Referral ini sudah di-void sebelumnya dengan alasan yang berbeda.',
                    409,
                    'referral',
                );
            }

            if (! $lockedReferral->isIssued()) {
                throw new ReferralManagementException(
                    'Hanya referral issued yang bisa di-void.',
                    409,
                    'referral',
                );
            }

            $before = $this->referralAuditSnapshot($lockedReferral);

            $lockedReferral->update([
                'status' => 'voided',
                'void_reason' => $payload['void_reason'],
                'voided_at' => $lockedReferral->voided_at ?? now(),
                'voided_by_user_id' => $actor->getKey(),
            ]);
            $lockedReferral = $lockedReferral->fresh($this->referralRelations());

            $this->auditLogService->log(
                module: 'referral_management',
                action: 'void_referral',
                auditable: $lockedReferral,
                description: sprintf('Referral %s di-void oleh %s.', $lockedReferral->referral_no, $actor->email),
                before: $before,
                after: $this->referralAuditSnapshot($lockedReferral),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $lockedReferral->visit_registration_id,
                    'branch_id' => $lockedReferral->branch_id,
                ],
            );

            return ['referral' => $lockedReferral, 'changed' => true];
        });
    }

    public function reissueReferral(PatientReferral $referral, User $actor): array
    {
        return $this->transactional(function () use ($referral, $actor): array {
            $lockedReferral = $this->lockReferral($referral->getKey());

            if (! in_array($lockedReferral->status, ['issued', 'voided'], true)) {
                throw new ReferralManagementException(
                    'Hanya referral issued atau voided yang bisa direissue.',
                    409,
                    'referral',
                );
            }

            $visit = $this->resolveManagedVisit((int) $lockedReferral->visit_registration_id, true);

            $newReferral = PatientReferral::query()->create([
                'visit_registration_id' => $lockedReferral->visit_registration_id,
                'patient_id' => $lockedReferral->patient_id,
                'patient_branch_record_id' => $lockedReferral->patient_branch_record_id,
                'branch_id' => $lockedReferral->branch_id,
                'section_id' => $lockedReferral->section_id,
                'doctor_id' => $lockedReferral->doctor_id,
                'referral_destination_id' => $lockedReferral->referral_destination_id,
                'reissued_from_id' => $lockedReferral->id,
                'issued_by_user_id' => $actor->getKey(),
                'referral_no' => $this->branchDocumentNumberService->nextReferralNumber($visit->branch),
                'status' => 'issued',
                'destination_type' => $lockedReferral->destination_type,
                'destination_name' => $lockedReferral->destination_name,
                'destination_address' => $lockedReferral->destination_address,
                'destination_phone' => $lockedReferral->destination_phone,
                'diagnosis_summary' => $lockedReferral->diagnosis_summary,
                'clinical_summary' => $lockedReferral->clinical_summary,
                'treatment_summary' => $lockedReferral->treatment_summary,
                'reason' => $lockedReferral->reason,
                'notes' => $lockedReferral->notes,
                'doctor_name_snapshot' => $lockedReferral->doctor_name_snapshot,
                'doctor_specialization_snapshot' => $lockedReferral->doctor_specialization_snapshot,
                'doctor_signature_path_snapshot' => $lockedReferral->doctor_signature_path_snapshot,
                'issued_at' => now(),
            ]);
            $newReferral = $newReferral->fresh($this->printRelations());

            $this->auditLogService->log(
                module: 'referral_management',
                action: 'reissue_referral',
                auditable: $newReferral,
                description: sprintf('Referral %s direissue dari referral %s oleh %s.', $newReferral->referral_no, $lockedReferral->referral_no, $actor->email),
                after: $this->referralAuditSnapshot($newReferral),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $newReferral->visit_registration_id,
                    'branch_id' => $newReferral->branch_id,
                    'source_referral_id' => $lockedReferral->getKey(),
                ],
            );

            return ['referral' => $newReferral, 'changed' => true];
        });
    }

    public function deleteReferral(PatientReferral $referral, User $actor): array
    {
        return $this->transactional(function () use ($referral, $actor): array {
            $lockedReferral = $this->lockReferral($referral->getKey());
            $this->ensureDraft($lockedReferral);

            $before = $this->referralAuditSnapshot($lockedReferral);
            $lockedReferral->delete();

            $this->auditLogService->log(
                module: 'referral_management',
                action: 'delete_referral_draft',
                auditable: $lockedReferral,
                description: sprintf('Draft referral %s dihapus oleh %s.', $lockedReferral->getKey(), $actor->email),
                before: $before,
                after: [],
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'visit_registration_id' => $lockedReferral->visit_registration_id,
                    'branch_id' => $lockedReferral->branch_id,
                ],
            );

            return ['referral' => $lockedReferral, 'changed' => true];
        });
    }

    public function destinationPayload(ReferralDestination $destination): array
    {
        return [
            'id' => $destination->getKey(),
            'code' => $destination->code,
            'destination_type' => $destination->destination_type,
            'name' => $destination->name,
            'address' => $destination->address,
            'contact_person' => $destination->contact_person,
            'phone' => $destination->phone,
            'notes' => $destination->notes,
            'is_active' => $destination->is_active,
            'referrals_count' => (int) ($destination->referrals_count ?? 0),
        ];
    }

    public function referralPayload(PatientReferral $referral): array
    {
        $referral->loadMissing($this->referralRelations());

        return [
            'id' => $referral->getKey(),
            'visit_registration_id' => $referral->visit_registration_id,
            'referral_no' => $referral->referral_no,
            'status' => $referral->status,
            'patient_name' => $referral->patient?->full_name,
            'medical_record_no' => $referral->patientBranchRecord?->medical_record_no,
            'destination_name' => $referral->destination_name ?: $referral->destination?->name,
            'doctor_name' => $referral->doctor_name_snapshot ?: $referral->doctor?->displayName(),
            'diagnosis_summary' => $referral->diagnosis_summary,
            'clinical_summary' => $referral->clinical_summary,
            'treatment_summary' => $referral->treatment_summary,
            'reason' => $referral->reason,
            'notes' => $referral->notes,
            'void_reason' => $referral->void_reason,
            'issued_at' => $referral->issued_at?->toIso8601String(),
            'voided_at' => $referral->voided_at?->toIso8601String(),
            'printed_at' => $referral->printed_at?->toIso8601String(),
        ];
    }

    private function transactional(callable $callback): mixed
    {
        try {
            return DB::transaction($callback);
        } catch (ReferralManagementException $exception) {
            throw $exception;
        } catch (ValidationException $exception) {
            throw $this->mapValidationException($exception);
        }
    }

    private function mapValidationException(ValidationException $exception): ReferralManagementException
    {
        $errors = $exception->errors();
        $key = (string) (array_key_first($errors) ?? 'referral');
        $message = (string) ($errors[$key][0] ?? $exception->getMessage());

        return new ReferralManagementException($message, 422, $key);
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'destination_search' => trim((string) ($filters['destination_search'] ?? '')),
            'destination_status' => (string) ($filters['destination_status'] ?? ''),
            'destination_type' => (string) ($filters['destination_type'] ?? ''),
            'destination_sort_by' => (string) ($filters['destination_sort_by'] ?? 'name'),
            'destination_sort_direction' => (string) ($filters['destination_sort_direction'] ?? 'asc'),
            'destination_per_page' => (int) ($filters['destination_per_page'] ?? 10),
            'referral_search' => trim((string) ($filters['referral_search'] ?? '')),
            'referral_status' => (string) ($filters['referral_status'] ?? ''),
            'referral_sort_by' => (string) ($filters['referral_sort_by'] ?? 'issued_at'),
            'referral_sort_direction' => (string) ($filters['referral_sort_direction'] ?? 'desc'),
            'referral_per_page' => (int) ($filters['referral_per_page'] ?? 10),
        ];
    }

    private function summary(): array
    {
        $destinationQuery = ReferralDestination::query();
        $referralQuery = PatientReferral::query();

        return [
            'destination_total' => (clone $destinationQuery)->count(),
            'destination_active' => (clone $destinationQuery)->where('is_active', true)->count(),
            'referral_draft' => (clone $referralQuery)->where('status', 'draft')->count(),
            'referral_issued' => (clone $referralQuery)->where('status', 'issued')->count(),
            'referral_voided' => (clone $referralQuery)->where('status', 'voided')->count(),
        ];
    }

    private function destinationTable(array $filters): LengthAwarePaginator
    {
        return ReferralDestination::query()
            ->withCount('referrals')
            ->searchForManagement($filters['destination_search'])
            ->filterActiveState($filters['destination_status'])
            ->filterType($filters['destination_type'])
            ->orderByAllowed(
                $filters['destination_sort_by'],
                $filters['destination_sort_direction'],
                self::DESTINATION_ALLOWED_SORTS,
            )
            ->paginate($filters['destination_per_page'], ['*'], 'destination_page')
            ->withQueryString();
    }

    private function referralTable(array $filters): LengthAwarePaginator
    {
        return PatientReferral::query()
            ->with($this->referralRelations())
            ->searchForManagement($filters['referral_search'])
            ->filterStatus($filters['referral_status'])
            ->orderByAllowed(
                $filters['referral_sort_by'],
                $filters['referral_sort_direction'],
                self::REFERRAL_ALLOWED_SORTS,
            )
            ->paginate($filters['referral_per_page'], ['*'], 'referral_page')
            ->withQueryString();
    }

    private function visitOptions(): Collection
    {
        return VisitRegistration::query()
            ->with([
                'patient:id,full_name,phone',
                'patientBranchRecord:id,medical_record_no',
                'branch:id,name,code',
                'section:id,name,code',
                'medicalRecord:id,visit_registration_id,doctor_id,status,assessment,plan,subjective',
                'medicalRecord.diagnoses.icd10Code:id,code,name_en',
                'medicalRecord.doctor:id,full_name,title_prefix,title_suffix,specialization',
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
                'care_stage',
            ]);
    }

    private function abilities(): array
    {
        $user = auth()->user();

        return [
            'create' => $user?->hasRole('super-admin') || ($user?->can('create referral management') ?? false),
            'edit' => $user?->hasRole('super-admin') || ($user?->can('edit referral management') ?? false),
            'delete' => $user?->hasRole('super-admin') || ($user?->can('delete referral management') ?? false),
            'issue' => $user?->hasRole('super-admin') || ($user?->can('issue referral management') ?? false),
            'print' => $user?->hasRole('super-admin') || ($user?->can('print referral management') ?? false),
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
            throw new ReferralManagementException('Visit registration tidak ditemukan.', 404, 'visit_registration_id');
        }

        if (! $visit->medicalRecord) {
            throw new ReferralManagementException(
                'Referral hanya bisa dibuat untuk visit yang sudah punya medical record.',
                422,
                'visit_registration_id',
            );
        }

        if ($visit->care_stage === 'cancelled') {
            throw new ReferralManagementException(
                'Visit yang dibatalkan tidak bisa dipakai untuk dokumen referral.',
                409,
                'visit_registration_id',
            );
        }

        return $visit;
    }

    private function resolveManagedDestination(int $destinationId, bool $lock = false): ReferralDestination
    {
        $query = ReferralDestination::query();

        if ($lock) {
            $query->lockForUpdate();
        }

        $destination = $query->find($destinationId);

        if ($destination === null) {
            throw new ReferralManagementException('Tujuan referral tidak ditemukan.', 404, 'referral_destination_id');
        }

        if (! $destination->is_active) {
            throw new ReferralManagementException(
                'Tujuan referral yang tidak aktif tidak bisa dipakai untuk draft baru.',
                409,
                'referral_destination_id',
            );
        }

        return $destination;
    }

    private function lockDestination(int $destinationId): ReferralDestination
    {
        $destination = ReferralDestination::query()
            ->lockForUpdate()
            ->find($destinationId);

        if ($destination === null) {
            throw new ReferralManagementException('Tujuan referral tidak ditemukan.', 404, 'destination');
        }

        return $destination;
    }

    private function lockReferral(int $referralId): PatientReferral
    {
        $referral = PatientReferral::query()
            ->with($this->referralRelations())
            ->lockForUpdate()
            ->find($referralId);

        if ($referral === null) {
            throw new ReferralManagementException('Referral tidak ditemukan.', 404, 'referral');
        }

        return $referral;
    }

    private function destinationAttributes(array $payload): array
    {
        return [
            'code' => strtoupper(trim($payload['code'])),
            'destination_type' => $payload['destination_type'],
            'name' => trim($payload['name']),
            'address' => $payload['address'] ?? null,
            'contact_person' => $payload['contact_person'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'notes' => $payload['notes'] ?? null,
            'is_active' => (bool) $payload['is_active'],
        ];
    }

    private function referralAttributes(VisitRegistration $visit, ReferralDestination $destination, array $payload): array
    {
        $medicalRecord = $visit->medicalRecord;

        return [
            'visit_registration_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'patient_branch_record_id' => $visit->patient_branch_record_id,
            'branch_id' => $visit->branch_id,
            'section_id' => $visit->section_id,
            'doctor_id' => $medicalRecord?->doctor_id ?: $visit->doctor_id,
            'referral_destination_id' => $destination->id,
            'status' => 'draft',
            'destination_type' => $destination->destination_type,
            'destination_name' => $destination->name,
            'destination_address' => $destination->address,
            'destination_phone' => $destination->phone,
            'diagnosis_summary' => $payload['diagnosis_summary'] ?: $this->defaultDiagnosisSummary($visit),
            'clinical_summary' => $payload['clinical_summary'] ?: $this->defaultClinicalSummary($visit),
            'treatment_summary' => $payload['treatment_summary'] ?: $medicalRecord?->plan,
            'reason' => trim($payload['reason']),
            'notes' => $payload['notes'] ?? null,
        ];
    }

    private function destinationMatchesDesiredState(ReferralDestination $destination, array $payload): bool
    {
        $attributes = $this->destinationAttributes($payload);

        return $destination->code === $attributes['code']
            && $destination->destination_type === $attributes['destination_type']
            && $destination->name === $attributes['name']
            && $destination->address === $attributes['address']
            && $destination->contact_person === $attributes['contact_person']
            && $destination->phone === $attributes['phone']
            && $destination->notes === $attributes['notes']
            && (bool) $destination->is_active === (bool) $attributes['is_active'];
    }

    private function referralMatchesDesiredState(
        PatientReferral $referral,
        VisitRegistration $visit,
        ReferralDestination $destination,
        array $payload,
    ): bool {
        $attributes = $this->referralAttributes($visit, $destination, $payload);

        return (int) $referral->visit_registration_id === (int) $attributes['visit_registration_id']
            && (int) $referral->patient_id === (int) $attributes['patient_id']
            && (int) ($referral->patient_branch_record_id ?? 0) === (int) ($attributes['patient_branch_record_id'] ?? 0)
            && (int) $referral->branch_id === (int) $attributes['branch_id']
            && (int) ($referral->section_id ?? 0) === (int) ($attributes['section_id'] ?? 0)
            && (int) ($referral->doctor_id ?? 0) === (int) ($attributes['doctor_id'] ?? 0)
            && (int) $referral->referral_destination_id === (int) $attributes['referral_destination_id']
            && $referral->status === $attributes['status']
            && $referral->destination_type === $attributes['destination_type']
            && $referral->destination_name === $attributes['destination_name']
            && $referral->destination_address === $attributes['destination_address']
            && $referral->destination_phone === $attributes['destination_phone']
            && $referral->diagnosis_summary === $attributes['diagnosis_summary']
            && $referral->clinical_summary === $attributes['clinical_summary']
            && $referral->treatment_summary === $attributes['treatment_summary']
            && $referral->reason === $attributes['reason']
            && $referral->notes === $attributes['notes'];
    }

    private function ensureDraft(PatientReferral $referral): void
    {
        if (! $referral->isDraft()) {
            throw new ReferralManagementException(
                'Hanya draft referral yang bisa diubah atau dihapus.',
                409,
                'referral',
            );
        }
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

    private function defaultClinicalSummary(VisitRegistration $visit): ?string
    {
        $medicalRecord = $visit->medicalRecord;

        if (! $medicalRecord) {
            return null;
        }

        return trim(collect([
            $medicalRecord->subjective ? 'Subjective: ' . $medicalRecord->subjective : null,
            $medicalRecord->assessment ? 'Assessment: ' . $medicalRecord->assessment : null,
        ])->filter()->implode(PHP_EOL));
    }

    private function destinationAuditSnapshot(ReferralDestination $destination): array
    {
        return [
            'id' => $destination->getKey(),
            'code' => $destination->code,
            'destination_type' => $destination->destination_type,
            'name' => $destination->name,
            'address' => $destination->address,
            'contact_person' => $destination->contact_person,
            'phone' => $destination->phone,
            'notes' => $destination->notes,
            'is_active' => $destination->is_active,
        ];
    }

    private function referralAuditSnapshot(PatientReferral $referral): array
    {
        $referral->loadMissing($this->referralRelations());

        return [
            'id' => $referral->getKey(),
            'visit_registration_id' => $referral->visit_registration_id,
            'patient_id' => $referral->patient_id,
            'patient_branch_record_id' => $referral->patient_branch_record_id,
            'branch_id' => $referral->branch_id,
            'section_id' => $referral->section_id,
            'doctor_id' => $referral->doctor_id,
            'referral_destination_id' => $referral->referral_destination_id,
            'referral_no' => $referral->referral_no,
            'status' => $referral->status,
            'destination_type' => $referral->destination_type,
            'destination_name' => $referral->destination_name,
            'destination_address' => $referral->destination_address,
            'destination_phone' => $referral->destination_phone,
            'diagnosis_summary' => $referral->diagnosis_summary,
            'clinical_summary' => $referral->clinical_summary,
            'treatment_summary' => $referral->treatment_summary,
            'reason' => $referral->reason,
            'notes' => $referral->notes,
            'doctor_name_snapshot' => $referral->doctor_name_snapshot,
            'doctor_specialization_snapshot' => $referral->doctor_specialization_snapshot,
            'doctor_signature_path_snapshot' => $referral->doctor_signature_path_snapshot,
            'issued_at' => $referral->issued_at?->toIso8601String(),
            'voided_at' => $referral->voided_at?->toIso8601String(),
            'printed_at' => $referral->printed_at?->toIso8601String(),
            'void_reason' => $referral->void_reason,
        ];
    }

    private function referralRelations(): array
    {
        return [
            'patient:id,full_name,gender,date_of_birth,phone',
            'patientBranchRecord:id,medical_record_no',
            'branch:id,clinic_id,name,code,address,phone',
            'branch.clinic:id,name,address,phone',
            'section:id,name,code',
            'doctor:id,full_name,title_prefix,title_suffix,specialization,signature_path',
            'destination:id,code,destination_type,name,address,phone,contact_person',
            'visitRegistration.medicalRecord:id,visit_registration_id,status,doctor_id',
            'issuedBy:id,name',
            'voidedBy:id,name',
            'reissuedFrom:id,referral_no',
        ];
    }

    private function printRelations(): array
    {
        return $this->referralRelations();
    }
}
