<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcedureMaster extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'performer_scope',
        'requires_doctor_order',
        'default_fee',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_doctor_order' => 'boolean',
            'default_fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function branchPrices(): HasMany
    {
        return $this->hasMany(ProcedureBranchPrice::class)
            ->orderBy('branch_id');
    }

    public function visitProcedures(): HasMany
    {
        return $this->hasMany(VisitProcedure::class)
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
