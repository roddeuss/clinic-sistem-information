<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DoctorLetter extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'visit_registration_id',
        'patient_id',
        'patient_branch_record_id',
        'branch_id',
        'section_id',
        'doctor_id',
        'reissued_from_id',
        'issued_by_user_id',
        'voided_by_user_id',
        'letter_no',
        'letter_type',
        'status',
        'issue_date',
        'diagnosis_summary',
        'notes',
        'sick_start_date',
        'sick_end_date',
        'sick_total_days',
        'healthy_statement',
        'control_date',
        'control_notes',
        'drug_test_date',
        'drug_test_method',
        'drug_test_result',
        'drug_free_statement',
        'doctor_name_snapshot',
        'doctor_specialization_snapshot',
        'doctor_signature_path_snapshot',
        'sip_number_snapshot',
        'issued_at',
        'voided_at',
        'printed_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'sick_start_date' => 'date',
            'sick_end_date' => 'date',
            'control_date' => 'date',
            'drug_test_date' => 'date',
            'sick_total_days' => 'integer',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }

    public function visitRegistration(): BelongsTo
    {
        return $this->belongsTo(VisitRegistration::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function patientBranchRecord(): BelongsTo
    {
        return $this->belongsTo(PatientBranchRecord::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function reissuedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reissued_from_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    public function scopeSearchForManagement(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('letter_no', 'like', '%' . $search . '%')
                ->orWhere('doctor_name_snapshot', 'like', '%' . $search . '%')
                ->orWhere('diagnosis_summary', 'like', '%' . $search . '%')
                ->orWhereHas('patient', function (Builder $patientQuery) use ($search): void {
                    $patientQuery
                        ->where('full_name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                })
                ->orWhereHas('patientBranchRecord', function (Builder $recordQuery) use ($search): void {
                    $recordQuery->where('medical_record_no', 'like', '%' . $search . '%');
                });
        });
    }

    public function scopeFilterStatus(Builder $query, string $status): void
    {
        if ($status === '') {
            return;
        }

        $query->where('status', $status);
    }

    public function scopeFilterLetterType(Builder $query, string $letterType): void
    {
        if ($letterType === '') {
            return;
        }

        $query->where('letter_type', $letterType);
    }

    public function scopeOrderByAllowed(Builder $query, string $sortBy, string $direction, array $allowedSorts): void
    {
        $column = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'issued_at';
        $sortDirection = $direction === 'asc' ? 'asc' : 'desc';

        match ($column) {
            'letter_no' => $query->orderBy('letter_no', $sortDirection),
            'letter_type' => $query->orderBy('letter_type', $sortDirection),
            'status' => $query->orderBy('status', $sortDirection),
            'created_at' => $query->orderBy('created_at', $sortDirection),
            default => $query->orderByRaw('CASE WHEN issued_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('issued_at', $sortDirection),
        };

        $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
