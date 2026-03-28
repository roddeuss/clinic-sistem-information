<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receivable extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'branch_id',
        'patient_id',
        'patient_branch_record_id',
        'status',
        'due_date',
        'opened_at',
        'opened_by_user_id',
        'extended_at',
        'extended_by_user_id',
        'extension_reason',
        'settled_at',
        'settled_by_user_id',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancel_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'opened_at' => 'datetime',
            'extended_at' => 'datetime',
            'settled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function patientBranchRecord(): BelongsTo
    {
        return $this->belongsTo(PatientBranchRecord::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function extendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'extended_by_user_id');
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}
