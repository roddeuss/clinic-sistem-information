<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Patient;
use App\Models\PatientBranchRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PatientRecordService
{
    public function ensureBranchRecord(Patient $patient, Branch $branch): PatientBranchRecord
    {
        return DB::transaction(function () use ($patient, $branch): PatientBranchRecord {
            $existing = PatientBranchRecord::query()
                ->where('patient_id', $patient->id)
                ->where('branch_id', $branch->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            return PatientBranchRecord::query()->create([
                'patient_id' => $patient->id,
                'branch_id' => $branch->id,
                'medical_record_no' => $this->nextMedicalRecordNo($branch),
            ]);
        });
    }

    public function duplicateCandidates(array $attributes, ?int $ignorePatientId = null): Collection
    {
        $name = trim((string) ($attributes['full_name'] ?? ''));
        $dateOfBirth = $attributes['date_of_birth'] ?? null;
        $phone = trim((string) ($attributes['phone'] ?? ''));

        if ($name === '' || ! $dateOfBirth || $phone === '') {
            return collect();
        }

        return Patient::query()
            ->with('branchRecords.branch:id,name,code')
            ->when($ignorePatientId, fn ($query) => $query->whereKeyNot($ignorePatientId))
            ->where('full_name', $name)
            ->whereDate('date_of_birth', $dateOfBirth)
            ->where('phone', $phone)
            ->limit(5)
            ->get();
    }

    private function nextMedicalRecordNo(Branch $branch): string
    {
        $lastRecord = PatientBranchRecord::query()
            ->where('branch_id', $branch->id)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $lastNumber = 0;

        if ($lastRecord && preg_match('/(\d+)$/', $lastRecord->medical_record_no, $matches) === 1) {
            $lastNumber = (int) $matches[1];
        }

        return sprintf('P-%05d', $lastNumber + 1);
    }
}
