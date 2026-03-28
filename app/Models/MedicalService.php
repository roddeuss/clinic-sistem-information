<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicalService extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'service_type',
        'description',
        'default_fee',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function branchPrices(): HasMany
    {
        return $this->hasMany(MedicalServiceBranchPrice::class)
            ->orderBy('branch_id');
    }

    public function visitServices(): HasMany
    {
        return $this->hasMany(VisitMedicalService::class)
            ->orderByDesc('ordered_at')
            ->orderByDesc('id');
    }

    public function scopeSearchForManagement(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('code', 'like', '%' . $search . '%')
                ->orWhere('name', 'like', '%' . $search . '%')
                ->orWhere('service_type', 'like', '%' . $search . '%')
                ->orWhere('description', 'like', '%' . $search . '%');
        });
    }

    public function scopeFilterActiveState(Builder $query, string $masterStatus): void
    {
        if ($masterStatus === '') {
            return;
        }

        $query->where('is_active', $masterStatus === 'active');
    }

    public function scopeOrderByAllowed(Builder $query, string $sortBy, string $direction, array $allowedSorts): void
    {
        $column = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'name';
        $sortDirection = $direction === 'desc' ? 'desc' : 'asc';

        $query->orderBy($column, $sortDirection)->orderBy('id');
    }
}
