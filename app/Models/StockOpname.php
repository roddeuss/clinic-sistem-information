<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockOpname extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'created_by_user_id',
        'finalized_by_user_id',
        'cancelled_by_user_id',
        'opname_no',
        'status',
        'opname_date',
        'finalized_at',
        'cancelled_at',
        'cancel_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opname_date' => 'date',
            'finalized_at' => 'datetime',
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

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class)
            ->orderBy('id');
    }
}
