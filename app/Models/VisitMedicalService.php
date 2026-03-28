<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitMedicalService extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_registration_id',
        'branch_id',
        'section_id',
        'medical_service_id',
        'ordered_by_user_id',
        'performed_by_user_id',
        'quantity',
        'unit_price',
        'subtotal',
        'status',
        'ordered_at',
        'completed_at',
        'cancelled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'ordered_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function visitRegistration(): BelongsTo
    {
        return $this->belongsTo(VisitRegistration::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function medicalService(): BelongsTo
    {
        return $this->belongsTo(MedicalService::class);
    }

    public function orderedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by_user_id');
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function scopeSearchForManagement(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->whereHas('visitRegistration.patient', function (Builder $patientQuery) use ($search): void {
                    $patientQuery
                        ->where('full_name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                })
                ->orWhereHas('visitRegistration.patientBranchRecord', function (Builder $recordQuery) use ($search): void {
                    $recordQuery->where('medical_record_no', 'like', '%' . $search . '%');
                })
                ->orWhereHas('medicalService', function (Builder $serviceQuery) use ($search): void {
                    $serviceQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('code', 'like', '%' . $search . '%');
                })
                ->orWhere('notes', 'like', '%' . $search . '%');
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
        $column = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'ordered_at';
        $sortDirection = $direction === 'asc' ? 'asc' : 'desc';

        match ($column) {
            'status' => $query->orderBy('status', $sortDirection),
            'subtotal' => $query->orderBy('subtotal', $sortDirection),
            'created_at' => $query->orderBy('created_at', $sortDirection),
            default => $query->orderBy('ordered_at', $sortDirection),
        };

        $query->orderByDesc('id');
    }
}
