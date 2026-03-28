<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaboratoryOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_registration_id',
        'branch_id',
        'section_id',
        'laboratory_test_id',
        'ordered_by_doctor_id',
        'provider_type',
        'partner_name',
        'external_reference_no',
        'status',
        'unit_price',
        'ordered_at',
        'sample_collected_at',
        'sent_to_partner_at',
        'resulted_at',
        'resulted_by_user_id',
        'reviewed_at',
        'reviewed_by_user_id',
        'result_attachment_path',
        'result_summary',
        'result_impression',
        'request_printed_at',
        'result_printed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'ordered_at' => 'datetime',
            'sample_collected_at' => 'datetime',
            'sent_to_partner_at' => 'datetime',
            'resulted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'request_printed_at' => 'datetime',
            'result_printed_at' => 'datetime',
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

    public function laboratoryTest(): BelongsTo
    {
        return $this->belongsTo(LaboratoryTest::class);
    }

    public function orderedByDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'ordered_by_doctor_id');
    }

    public function resultedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resulted_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(LaboratoryResultEntry::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function isReviewed(): bool
    {
        return $this->status === 'reviewed';
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
                ->orWhereHas('laboratoryTest', function (Builder $testQuery) use ($search): void {
                    $testQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('code', 'like', '%' . $search . '%');
                })
                ->orWhere('partner_name', 'like', '%' . $search . '%')
                ->orWhere('external_reference_no', 'like', '%' . $search . '%')
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

    public function scopeFilterProviderType(Builder $query, string $providerType): void
    {
        if ($providerType === '') {
            return;
        }

        $query->where('provider_type', $providerType);
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
            'unit_price' => $query->orderBy('unit_price', $sortDirection),
            'created_at' => $query->orderBy('created_at', $sortDirection),
            default => $query->orderBy('ordered_at', $sortDirection),
        };

        $query->orderByDesc('id');
    }
}
