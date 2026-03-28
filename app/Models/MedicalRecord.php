<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_registration_id',
        'patient_id',
        'branch_id',
        'section_id',
        'doctor_id',
        'subjective',
        'objective',
        'assessment',
        'plan',
        'diagnosis_notes',
        'status',
        'finalized_at',
        'finalized_by_user_id',
        'reopen_requested_at',
        'reopen_requested_by_user_id',
        'reopen_request_reason',
        'reopened_at',
        'reopened_by_user_id',
        'reopen_approved_by_user_id',
        'reopen_approval_reason',
    ];

    protected function casts(): array
    {
        return [
            'finalized_at' => 'datetime',
            'reopen_requested_at' => 'datetime',
            'reopened_at' => 'datetime',
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

    public function diagnoses(): HasMany
    {
        return $this->hasMany(MedicalRecordDiagnosis::class)
            ->orderBy('diagnosis_type')
            ->orderBy('sort_order');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(MedicalRecordAudit::class)
            ->orderByDesc('created_at');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    public function reopenRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopen_requested_by_user_id');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }

    public function reopenApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopen_approved_by_user_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'reopened'], true);
    }

    public function isFinalized(): bool
    {
        return $this->status === 'final';
    }

    public function isAwaitingReopenApproval(): bool
    {
        return $this->status === 'reopen_requested';
    }
}
