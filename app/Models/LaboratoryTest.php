<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaboratoryTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'diagnostic_category',
        'sample_type',
        'default_provider_type',
        'result_entry_mode',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(LaboratoryTestParameter::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function branchPrices(): HasMany
    {
        return $this->hasMany(LaboratoryTestBranchPrice::class)
            ->orderBy('branch_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(LaboratoryOrder::class)
            ->orderByDesc('ordered_at');
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
                ->orWhere('diagnostic_category', 'like', '%' . $search . '%')
                ->orWhere('sample_type', 'like', '%' . $search . '%')
                ->orWhere('description', 'like', '%' . $search . '%');
        });
    }

    public function scopeFilterActiveState(Builder $query, string $status): void
    {
        if ($status === '') {
            return;
        }

        $query->where('is_active', $status === 'active');
    }

    public function scopeOrderByAllowed(Builder $query, string $sortBy, string $direction, array $allowedSorts): void
    {
        $column = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'name';
        $sortDirection = $direction === 'desc' ? 'desc' : 'asc';

        $query->orderBy($column, $sortDirection)->orderBy('id');
    }
}
