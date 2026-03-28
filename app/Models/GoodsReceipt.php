<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'branch_id',
        'supplier_id',
        'received_by_user_id',
        'cancelled_by_user_id',
        'receipt_no',
        'status',
        'received_at',
        'total_amount',
        'cancelled_at',
        'cancel_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'date',
            'total_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class)
            ->orderBy('id');
    }
}
