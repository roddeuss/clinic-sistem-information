<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'gender',
        'date_of_birth',
        'nik',
        'phone',
        'email',
        'province_code',
        'province_name',
        'city_code',
        'city_name',
        'district_code',
        'district_name',
        'village_code',
        'village_name',
        'address_line',
        'allergy_notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function branchRecords(): HasMany
    {
        return $this->hasMany(PatientBranchRecord::class)
            ->orderByDesc('created_at');
    }

    public function visitRegistrations(): HasMany
    {
        return $this->hasMany(VisitRegistration::class)
            ->orderByDesc('visit_date')
            ->orderByDesc('created_at');
    }

    public function queueTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class)
            ->orderByDesc('queue_date')
            ->orderByDesc('created_at');
    }

    public function vitalSignRecords(): HasMany
    {
        return $this->hasMany(VitalSignRecord::class)
            ->orderByDesc('recorded_at')
            ->orderByDesc('created_at');
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class)
            ->orderByDesc('created_at');
    }

    public function scopeSearchForManagement(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $searchQuery) use ($search): void {
            $searchQuery
                ->where('full_name', 'like', '%' . $search . '%')
                ->orWhere('nik', 'like', '%' . $search . '%')
                ->orWhere('phone', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%')
                ->orWhere('province_name', 'like', '%' . $search . '%')
                ->orWhere('city_name', 'like', '%' . $search . '%')
                ->orWhere('district_name', 'like', '%' . $search . '%')
                ->orWhere('village_name', 'like', '%' . $search . '%')
                ->orWhereHas('branchRecords', function (Builder $recordQuery) use ($search): void {
                    $recordQuery
                        ->where('medical_record_no', 'like', '%' . $search . '%')
                        ->orWhereHas('branch', function (Builder $branchQuery) use ($search): void {
                            $branchQuery
                                ->where('name', 'like', '%' . $search . '%')
                                ->orWhere('code', 'like', '%' . $search . '%');
                        });
                });
        });
    }

    public function scopeFilterBranch(Builder $query, string $branchId): void
    {
        if ($branchId === '') {
            return;
        }

        $query->whereHas('branchRecords', function (Builder $recordQuery) use ($branchId): void {
            $recordQuery->where('branch_id', $branchId);
        });
    }

    public function scopeFilterStatus(Builder $query, string $status): void
    {
        if ($status === '') {
            return;
        }

        $query->where('is_active', $status === 'active');
    }

    public function scopeOrderByAllowed(Builder $query, string $sortBy, string $direction, array $allowedSorts): void
    {
        $column = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'full_name';
        $sortDirection = $direction === 'desc' ? 'desc' : 'asc';

        $query->orderBy($column, $sortDirection);

        if ($column !== 'full_name') {
            $query->orderBy('full_name');
        }
    }
}
