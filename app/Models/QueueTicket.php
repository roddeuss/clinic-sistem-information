<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_registration_id',
        'patient_id',
        'patient_branch_record_id',
        'branch_id',
        'section_id',
        'counter_id',
        'doctor_id',
        'called_by_user_id',
        'queue_date',
        'queue_number',
        'queue_code',
        'status',
        'called_at',
        'serving_at',
        'completed_at',
        'skipped_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'queue_date' => 'date',
            'queue_number' => 'integer',
            'called_at' => 'datetime',
            'serving_at' => 'datetime',
            'completed_at' => 'datetime',
            'skipped_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function calledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'called_by_user_id');
    }

    public function scopeSearchForManagement(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $searchQuery) use ($search): void {
            $searchQuery
                ->where('queue_code', 'like', '%' . $search . '%')
                ->orWhere('status', 'like', '%' . $search . '%')
                ->orWhereHas('patient', function (Builder $patientQuery) use ($search): void {
                    $patientQuery
                        ->where('full_name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                })
                ->orWhereHas('patientBranchRecord', function (Builder $recordQuery) use ($search): void {
                    $recordQuery->where('medical_record_no', 'like', '%' . $search . '%');
                })
                ->orWhereHas('doctor', function (Builder $doctorQuery) use ($search): void {
                    $doctorQuery->where('full_name', 'like', '%' . $search . '%');
                })
                ->orWhereHas('section', function (Builder $sectionQuery) use ($search): void {
                    $sectionQuery->where('name', 'like', '%' . $search . '%');
                })
                ->orWhereHas('counter', function (Builder $counterQuery) use ($search): void {
                    $counterQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('code', 'like', '%' . $search . '%');
                });
        });
    }

    public function scopeFilterDate(Builder $query, string $date): void
    {
        if ($date === '') {
            return;
        }

        $query->whereDate('queue_date', $date);
    }

    public function scopeFilterStatus(Builder $query, string $status): void
    {
        if ($status === '') {
            return;
        }

        $query->where('status', $status);
    }

    public function scopeFilterSection(Builder $query, string $sectionId): void
    {
        if ($sectionId === '') {
            return;
        }

        $query->where('section_id', $sectionId);
    }

    public function scopeOrderByAllowed(Builder $query, string $sortBy, string $direction, array $allowedSorts): void
    {
        $column = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'queue_date';
        $sortDirection = $direction === 'asc' ? 'asc' : 'desc';

        if (in_array($column, ['called_at', 'completed_at'], true)) {
            $query
                ->orderByRaw(sprintf('case when %s is null then 1 else 0 end', $column))
                ->orderBy($column, $sortDirection);
        } else {
            $query->orderBy($column, $sortDirection);
        }

        if ($column !== 'queue_date') {
            $query->orderByDesc('queue_date');
        }

        if ($column !== 'queue_number') {
            $query->orderBy('queue_number');
        }

        $query->orderByDesc('id');
    }
}
