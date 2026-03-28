<?php

namespace App\Modules\Patients\Services;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Modules\Patients\Exceptions\PatientManagementException;
use App\Services\AuditLogService;
use App\Services\PatientRecordService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PatientService
{
    private const ALLOWED_SORTS = [
        'full_name',
        'created_at',
        'date_of_birth',
        'is_active',
    ];

    public function __construct(
        private readonly PatientRecordService $patientRecordService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        return [
            'filters' => $filters,
            'patients' => $this->patientTable($filters),
            'branchOptions' => Branch::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'abilities' => $this->abilities(),
            'sortOptions' => $this->sortOptions(),
            'perPageOptions' => [10, 25, 50, 100],
        ];
    }

    public function createPatient(array $payload, User $actor): array
    {
        $duplicates = $this->duplicateWarnings($payload);

        $patient = DB::transaction(function () use ($payload): Patient {
            $patient = Patient::query()->create($this->patientAttributes($payload));

            if (! empty($payload['branch_id'])) {
                $branch = Branch::query()->select('id', 'name', 'code')->findOrFail($payload['branch_id']);
                $this->patientRecordService->ensureBranchRecord($patient, $branch);
            }

            return $patient;
        });

        $patient->load([
            'branchRecords' => fn ($query) => $query->with('branch:id,name,code')->orderByDesc('created_at'),
        ]);

        $this->auditLogService->log(
            module: 'patient_management',
            action: 'create',
            auditable: $patient,
            description: sprintf('Patient %s dibuat oleh %s.', $patient->full_name, $actor->email),
            after: $this->auditSnapshot($patient),
            meta: [
                'actor_user_id' => $actor->getKey(),
                'branch_id' => $payload['branch_id'] ?? null,
                'initial_branch_id' => $payload['branch_id'] ?? null,
            ],
        );

        return [
            'patient' => $patient,
            'duplicate_warnings' => $duplicates,
        ];
    }

    public function updatePatient(Patient $patient, array $payload, User $actor): array
    {
        $duplicates = $this->duplicateWarnings($payload, $patient->getKey());

        $changed = DB::transaction(function () use ($patient, $payload, $actor): bool {
            $lockedPatient = Patient::query()
                ->with([
                    'branchRecords' => fn ($query) => $query->with('branch:id,name,code')->orderByDesc('created_at'),
                ])
                ->lockForUpdate()
                ->findOrFail($patient->getKey());

            $before = $this->auditSnapshot($lockedPatient);
            $attributes = $this->patientAttributes($payload);
            $branch = ! empty($payload['branch_id'])
                ? Branch::query()->select('id', 'name', 'code')->findOrFail($payload['branch_id'])
                : null;
            $branchAlreadyLinked = $branch === null
                || $lockedPatient->branchRecords->contains(fn ($record) => $record->branch_id === $branch->getKey());

            $isDirty = $lockedPatient->full_name !== $attributes['full_name']
                || $lockedPatient->gender !== $attributes['gender']
                || optional($lockedPatient->date_of_birth)->toDateString() !== $attributes['date_of_birth']
                || $lockedPatient->nik !== $attributes['nik']
                || $lockedPatient->phone !== $attributes['phone']
                || $lockedPatient->email !== $attributes['email']
                || $lockedPatient->province_code !== $attributes['province_code']
                || $lockedPatient->province_name !== $attributes['province_name']
                || $lockedPatient->city_code !== $attributes['city_code']
                || $lockedPatient->city_name !== $attributes['city_name']
                || $lockedPatient->district_code !== $attributes['district_code']
                || $lockedPatient->district_name !== $attributes['district_name']
                || $lockedPatient->village_code !== $attributes['village_code']
                || $lockedPatient->village_name !== $attributes['village_name']
                || $lockedPatient->address_line !== $attributes['address_line']
                || $lockedPatient->allergy_notes !== $attributes['allergy_notes']
                || $lockedPatient->is_active !== $attributes['is_active'];

            if (! $isDirty && $branchAlreadyLinked) {
                return false;
            }

            if ($lockedPatient->is_active && ! $attributes['is_active']) {
                $this->guardArchivablePatient($lockedPatient);
            }

            $lockedPatient->fill($attributes);
            $lockedPatient->save();

            if ($branch !== null) {
                $this->patientRecordService->ensureBranchRecord($lockedPatient, $branch);
            }

            $lockedPatient->load([
                'branchRecords' => fn ($query) => $query->with('branch:id,name,code')->orderByDesc('created_at'),
            ]);

            $this->auditLogService->log(
                module: 'patient_management',
                action: 'update',
                auditable: $lockedPatient,
                description: sprintf('Patient %s diperbarui oleh %s.', $lockedPatient->full_name, $actor->email),
                before: $before,
                after: $this->auditSnapshot($lockedPatient),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'branch_id' => $branch?->getKey(),
                ],
            );

            return true;
        });

        return [
            'patient' => $patient->fresh([
                'branchRecords' => fn ($query) => $query->with('branch:id,name,code')->orderByDesc('created_at'),
            ]),
            'duplicate_warnings' => $duplicates,
            'changed' => $changed,
        ];
    }

    public function archivePatient(Patient $patient, User $actor, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($patient, $actor, $reason): bool {
            $lockedPatient = Patient::query()
                ->with([
                    'branchRecords' => fn ($query) => $query->with('branch:id,name,code')->orderByDesc('created_at'),
                ])
                ->lockForUpdate()
                ->findOrFail($patient->getKey());

            if (! $lockedPatient->is_active) {
                return false;
            }

            $this->guardArchivablePatient($lockedPatient);

            $before = $this->auditSnapshot($lockedPatient);

            $lockedPatient->forceFill([
                'is_active' => false,
            ])->save();

            $lockedPatient->load([
                'branchRecords' => fn ($query) => $query->with('branch:id,name,code')->orderByDesc('created_at'),
            ]);

            $this->auditLogService->log(
                module: 'patient_management',
                action: 'archive',
                auditable: $lockedPatient,
                description: sprintf('Patient %s dinonaktifkan oleh %s.', $lockedPatient->full_name, $actor->email),
                before: $before,
                after: $this->auditSnapshot($lockedPatient),
                meta: [
                    'actor_user_id' => $actor->getKey(),
                    'reason' => $reason,
                ],
            );

            return true;
        });
    }

    public function patientPayload(Patient $patient): array
    {
        $patient->loadMissing([
            'branchRecords' => fn ($query) => $query->with('branch:id,name,code')->orderByDesc('created_at'),
        ]);

        return [
            'id' => $patient->getKey(),
            'full_name' => $patient->full_name,
            'gender' => $patient->gender,
            'date_of_birth' => $patient->date_of_birth?->toDateString(),
            'nik' => $patient->nik,
            'phone' => $patient->phone,
            'email' => $patient->email,
            'province_code' => $patient->province_code,
            'province_name' => $patient->province_name,
            'city_code' => $patient->city_code,
            'city_name' => $patient->city_name,
            'district_code' => $patient->district_code,
            'district_name' => $patient->district_name,
            'village_code' => $patient->village_code,
            'village_name' => $patient->village_name,
            'address_line' => $patient->address_line,
            'allergy_notes' => $patient->allergy_notes,
            'is_active' => $patient->is_active,
            'branch_records' => $patient->branchRecords
                ->map(fn ($record): array => [
                    'id' => $record->getKey(),
                    'branch_id' => $record->branch_id,
                    'branch_code' => $record->branch?->code,
                    'branch_name' => $record->branch?->name,
                    'medical_record_no' => $record->medical_record_no,
                ])
                ->values()
                ->all(),
            'created_at' => $patient->created_at?->toIso8601String(),
            'updated_at' => $patient->updated_at?->toIso8601String(),
        ];
    }

    private function patientTable(array $filters): LengthAwarePaginator
    {
        return Patient::query()
            ->with([
                'branchRecords' => fn ($query) => $query
                    ->with('branch:id,name,code')
                    ->orderByDesc('created_at'),
            ])
            ->searchForManagement($filters['search'])
            ->filterBranch($filters['branch'])
            ->filterStatus($filters['status'])
            ->orderByAllowed($filters['sort_by'], $filters['sort_direction'], self::ALLOWED_SORTS)
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    private function patientAttributes(array $payload): array
    {
        return [
            'full_name' => trim($payload['full_name']),
            'gender' => $payload['gender'],
            'date_of_birth' => $payload['date_of_birth'],
            'nik' => $payload['nik'],
            'phone' => trim($payload['phone']),
            'email' => $payload['email'],
            'province_code' => $payload['province_code'],
            'province_name' => $payload['province_name'],
            'city_code' => $payload['city_code'],
            'city_name' => $payload['city_name'],
            'district_code' => $payload['district_code'],
            'district_name' => $payload['district_name'],
            'village_code' => $payload['village_code'],
            'village_name' => $payload['village_name'],
            'address_line' => $payload['address_line'],
            'allergy_notes' => $payload['allergy_notes'],
            'is_active' => $payload['is_active'],
        ];
    }

    private function duplicateWarnings(array $payload, ?int $ignorePatientId = null): array
    {
        return $this->patientRecordService
            ->duplicateCandidates($payload, $ignorePatientId)
            ->map(function (Patient $candidate): array {
                return [
                    'name' => $candidate->full_name,
                    'phone' => $candidate->phone,
                    'date_of_birth' => $candidate->date_of_birth?->format('d M Y'),
                    'records' => $candidate->branchRecords
                        ->map(fn ($record): string => ($record->branch?->code ?? '-') . ' - ' . $record->medical_record_no)
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    private function abilities(): array
    {
        $user = auth()->user();

        return [
            'create' => $user?->hasRole('super-admin') || ($user?->can('create patient management') ?? false),
            'edit' => $user?->hasRole('super-admin') || ($user?->can('edit patient management') ?? false),
            'delete' => $user?->hasRole('super-admin') || ($user?->can('delete patient management') ?? false),
        ];
    }

    private function sortOptions(): array
    {
        return [
            'full_name' => 'Nama patient',
            'created_at' => 'Tanggal dibuat',
            'date_of_birth' => 'Tanggal lahir',
            'is_active' => 'Status',
        ];
    }

    private function guardArchivablePatient(Patient $patient): void
    {
        $activeVisit = VisitRegistration::query()
            ->select('id')
            ->where('patient_id', $patient->getKey())
            ->whereNotIn('registration_status', ['completed', 'cancelled'])
            ->lockForUpdate()
            ->first();

        if ($activeVisit !== null) {
            throw new PatientManagementException(
                'Patient masih memiliki registrasi atau antrian aktif, jadi belum bisa dinonaktifkan.',
                409,
            );
        }
    }

    private function auditSnapshot(Patient $patient): array
    {
        return [
            'id' => $patient->getKey(),
            'full_name' => $patient->full_name,
            'gender' => $patient->gender,
            'date_of_birth' => $patient->date_of_birth?->toDateString(),
            'nik_masked' => $this->maskNik($patient->nik),
            'phone' => $patient->phone,
            'email' => $patient->email,
            'is_active' => $patient->is_active,
            'region' => collect([
                $patient->village_name,
                $patient->district_name,
                $patient->city_name,
                $patient->province_name,
            ])->filter()->implode(', '),
            'has_allergy_notes' => filled($patient->allergy_notes),
            'branch_records' => $patient->branchRecords
                ->map(fn ($record): array => [
                    'branch_id' => $record->branch_id,
                    'branch_code' => $record->branch?->code,
                    'medical_record_no' => $record->medical_record_no,
                ])
                ->values()
                ->all(),
        ];
    }

    private function maskNik(?string $nik): ?string
    {
        if (! filled($nik)) {
            return null;
        }

        if (strlen($nik) <= 4) {
            return str_repeat('*', strlen($nik));
        }

        return str_repeat('*', max(strlen($nik) - 4, 0)) . substr($nik, -4);
    }
}
