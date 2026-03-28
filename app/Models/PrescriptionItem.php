<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PrescriptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_id',
        'medicine_id',
        'item_type',
        'display_name',
        'route',
        'dose_amount',
        'dose_unit',
        'frequency',
        'duration_days',
        'instruction',
        'quantity_prescribed',
        'dispense_unit',
        'weight_snapshot_kg',
        'status',
        'closed_remaining_status',
        'closed_remaining_reason',
        'closed_remaining_at',
        'closed_remaining_by_user_id',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'dose_amount' => 'decimal:2',
            'quantity_prescribed' => 'decimal:2',
            'weight_snapshot_kg' => 'decimal:2',
            'closed_remaining_at' => 'datetime',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function compoundIngredients(): HasMany
    {
        return $this->hasMany(PrescriptionCompoundIngredient::class)
            ->orderBy('id');
    }

    public function dispenses(): HasMany
    {
        return $this->hasMany(PrescriptionDispense::class)
            ->orderByDesc('dispensed_at');
    }

    public function interactionOverrides(): HasMany
    {
        return $this->hasMany(PrescriptionInteractionOverride::class)
            ->orderByDesc('overridden_at')
            ->orderByDesc('id');
    }

    public function closedRemainingBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_remaining_by_user_id');
    }

    public function isInHouse(): bool
    {
        return $this->item_type === 'in_house';
    }

    public function isExternal(): bool
    {
        return $this->item_type === 'external';
    }

    public function isClosedExternally(): bool
    {
        return in_array($this->status, ['external', 'partial_external'], true);
    }

    public function isClosedCancelled(): bool
    {
        return in_array($this->status, ['cancelled', 'partial_cancelled'], true);
    }

    public function hasOpenFulfillment(): bool
    {
        if ($this->isExternal() || $this->isClosedExternally() || $this->isClosedCancelled()) {
            return false;
        }

        return in_array($this->status, ['pending', 'partial'], true);
    }

    public function isCompound(): bool
    {
        return $this->item_type === 'compound';
    }

    public function isDispensed(): bool
    {
        return in_array($this->status, ['dispensed', 'partial', 'partial_external', 'partial_cancelled'], true)
            || $this->dispenses()->exists();
    }

    public function scopeSearchForManagement(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('display_name', 'like', '%' . $search . '%')
                ->orWhere('instruction', 'like', '%' . $search . '%')
                ->orWhere('item_type', 'like', '%' . $search . '%')
                ->orWhereHas('medicine', function (Builder $medicineQuery) use ($search): void {
                    $medicineQuery
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('generic_name', 'like', '%' . $search . '%')
                        ->orWhere('code', 'like', '%' . $search . '%');
                })
                ->orWhereHas('prescription.visitRegistration.patient', function (Builder $patientQuery) use ($search): void {
                    $patientQuery
                        ->where('full_name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                })
                ->orWhereHas('prescription.visitRegistration.patientBranchRecord', function (Builder $recordQuery) use ($search): void {
                    $recordQuery->where('medical_record_no', 'like', '%' . $search . '%');
                })
                ->orWhereHas('prescription.visitRegistration.doctor', function (Builder $doctorQuery) use ($search): void {
                    $doctorQuery->where('full_name', 'like', '%' . $search . '%');
                });
        });
    }

    public function scopeFilterPrescriptionStatus(Builder $query, string $status): void
    {
        if ($status === '' || $status === 'none') {
            return;
        }

        $query->whereHas('prescription', fn (Builder $prescriptionQuery) => $prescriptionQuery->where('status', $status));
    }

    public function scopeOrderByAllowed(Builder $query, string $sortBy, string $direction, array $allowedSorts): void
    {
        $column = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'finalized_at';
        $sortDirection = $direction === 'asc' ? 'asc' : 'desc';

        match ($column) {
            'display_name' => $query->orderBy('display_name', $sortDirection),
            'status' => $query->orderBy('status', $sortDirection),
            'created_at' => $query->orderBy('created_at', $sortDirection),
            default => $query
                ->orderBy(
                    Prescription::query()
                        ->select('finalized_at')
                        ->whereColumn('prescriptions.id', 'prescription_items.prescription_id')
                        ->limit(1),
                    $sortDirection
                ),
        };

        $query->orderByDesc('id');
    }
}
