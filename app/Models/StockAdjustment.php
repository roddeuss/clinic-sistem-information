<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'created_by_user_id',
        'applied_by_user_id',
        'cancelled_by_user_id',
        'adjustment_no',
        'adjustment_type',
        'status',
        'adjustment_date',
        'total_items',
        'applied_at',
        'cancelled_at',
        'cancel_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_date' => 'date',
            'applied_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class)
            ->orderBy('id');
    }
}
