<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'description',
        'is_cash',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_cash' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)
            ->orderByDesc('created_at');
    }
}
