<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralDestination extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'destination_type',
        'name',
        'address',
        'contact_person',
        'phone',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(PatientReferral::class)
            ->orderByDesc('issued_at')
            ->orderByDesc('created_at');
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
                ->orWhere('contact_person', 'like', '%' . $search . '%')
                ->orWhere('phone', 'like', '%' . $search . '%')
                ->orWhere('address', 'like', '%' . $search . '%');
        });
    }

    public function scopeFilterActiveState(Builder $query, string $status): void
    {
        if ($status === '') {
            return;
        }

        $query->where('is_active', $status === 'active');
    }

    public function scopeFilterType(Builder $query, string $destinationType): void
    {
        if ($destinationType === '') {
            return;
        }

        $query->where('destination_type', $destinationType);
    }

    public function scopeOrderByAllowed(Builder $query, string $sortBy, string $direction, array $allowedSorts): void
    {
        $column = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'name';
        $sortDirection = $direction === 'desc' ? 'desc' : 'asc';

        match ($column) {
            'code' => $query->orderBy('code', $sortDirection),
            'destination_type' => $query->orderBy('destination_type', $sortDirection),
            'created_at' => $query->orderBy('created_at', $sortDirection),
            default => $query->orderBy('name', $sortDirection),
        };

        $query->orderBy('name')->orderByDesc('id');
    }
}
