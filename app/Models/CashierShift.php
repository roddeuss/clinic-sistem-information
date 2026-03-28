<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'counter_id',
        'branch_id',
        'shift_code',
        'status',
        'opening_balance',
        'opening_notes',
        'opened_at',
        'closed_at',
        'closing_balance',
        'expected_cash_total',
        'cash_variance',
        'closed_by_user_id',
        'closing_notes',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'expected_cash_total' => 'decimal:2',
            'cash_variance' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)
            ->orderByDesc('created_at');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
