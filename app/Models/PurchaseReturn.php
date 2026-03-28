<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'supplier_id',
        'created_by_user_id',
        'completed_by_user_id',
        'cancelled_by_user_id',
        'return_no',
        'status',
        'return_date',
        'total_amount',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'total_amount' => 'decimal:2',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class)
            ->orderBy('id');
    }
}
