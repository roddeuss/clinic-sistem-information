<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_registration_id',
        'patient_id',
        'branch_id',
        'section_id',
        'doctor_id',
        'status',
        'notes',
        'finalized_at',
        'finalized_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'finalized_at' => 'datetime',
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

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function interactionOverrides(): HasMany
    {
        return $this->hasMany(PrescriptionInteractionOverride::class)
            ->orderByDesc('overridden_at')
            ->orderByDesc('id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFinalizedWorkflow(): bool
    {
        return in_array($this->status, ['finalized', 'partial_dispensed', 'dispensed'], true);
    }

    public function scopeSearchForManagement(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('status', 'like', '%' . $search . '%')
                ->orWhereHas('visitRegistration.patient', function (Builder $patientQuery) use ($search): void {
                    $patientQuery
                        ->where('full_name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                })
                ->orWhereHas('visitRegistration.patientBranchRecord', function (Builder $recordQuery) use ($search): void {
                    $recordQuery->where('medical_record_no', 'like', '%' . $search . '%');
                })
                ->orWhereHas('visitRegistration.doctor', function (Builder $doctorQuery) use ($search): void {
                    $doctorQuery->where('full_name', 'like', '%' . $search . '%');
                });
        });
    }

    public function scopeFilterBranch(Builder $query, string $branchId): void
    {
        if ($branchId === '') {
            return;
        }

        $query->where('branch_id', $branchId);
    }

    public function scopeFilterStatus(Builder $query, string $status): void
    {
        if ($status === '') {
            return;
        }

        $query->where('status', $status);
    }

    public function scopeFilterDate(Builder $query, string $date): void
    {
        if ($date === '') {
            return;
        }

        $query->whereHas('visitRegistration', fn (Builder $visitQuery) => $visitQuery->whereDate('visit_date', $date));
    }

    public function scopeOrderByAllowed(Builder $query, string $sortBy, string $direction, array $allowedSorts): void
    {
        $column = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'finalized_at';
        $sortDirection = $direction === 'asc' ? 'asc' : 'desc';

        match ($column) {
            'status' => $query->orderBy('status', $sortDirection),
            'created_at' => $query->orderBy('created_at', $sortDirection),
            default => $query
                ->orderByRaw('case when finalized_at is null then 1 else 0 end')
                ->orderBy('finalized_at', $sortDirection),
        };

        if ($column !== 'finalized_at') {
            $query
                ->orderByRaw('case when finalized_at is null then 1 else 0 end')
                ->orderByDesc('finalized_at');
        }

        $query->orderByDesc('id');
    }
}
