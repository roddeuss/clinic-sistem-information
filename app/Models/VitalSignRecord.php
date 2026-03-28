<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalSignRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_registration_id',
        'patient_id',
        'branch_id',
        'section_id',
        'recorded_by_user_id',
        'systolic_bp',
        'diastolic_bp',
        'temperature_celsius',
        'pulse_rate',
        'respiratory_rate',
        'weight_kg',
        'height_cm',
        'spo2_percent',
        'bmi',
        'notes',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'temperature_celsius' => 'decimal:1',
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'bmi' => 'decimal:2',
            'recorded_at' => 'datetime',
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function scopeSearchForManagement(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $searchQuery) use ($search): void {
            $searchQuery
                ->whereHas('patient', function (Builder $patientQuery) use ($search): void {
                    $patientQuery
                        ->where('full_name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                })
                ->orWhereHas('visitRegistration.patientBranchRecord', function (Builder $recordQuery) use ($search): void {
                    $recordQuery->where('medical_record_no', 'like', '%' . $search . '%');
                })
                ->orWhereHas('section', function (Builder $sectionQuery) use ($search): void {
                    $sectionQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('code', 'like', '%' . $search . '%');
                })
                ->orWhereHas('recordedBy', function (Builder $userQuery) use ($search): void {
                    $userQuery->where('name', 'like', '%' . $search . '%');
                });
        });
    }

    public function scopeFilterDate(Builder $query, string $date): void
    {
        if ($date === '') {
            return;
        }

        $query->whereDate('recorded_at', $date);
    }

    public function scopeFilterBranch(Builder $query, string $branchId): void
    {
        if ($branchId === '') {
            return;
        }

        $query->where('branch_id', $branchId);
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
        $column = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'recorded_at';
        $sortDirection = $direction === 'asc' ? 'asc' : 'desc';

        if ($column === 'recorded_at') {
            $query
                ->orderByRaw('case when recorded_at is null then 1 else 0 end')
                ->orderBy('recorded_at', $sortDirection);
        } else {
            $query->orderBy($column, $sortDirection);
        }

        if ($column !== 'recorded_at') {
            $query->orderByDesc('recorded_at');
        }

        $query->orderByDesc('id');
    }
}
