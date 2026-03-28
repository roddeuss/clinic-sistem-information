<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicineBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'medicine_id',
        'supplier_id',
        'batch_number',
        'received_at',
        'expired_at',
        'quantity_received',
        'quantity_available',
        'purchase_cost',
        'supplier_name',
        'notes',
        'is_active',
        'quarantined_at',
        'quarantined_by_user_id',
        'quarantine_reason',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'date',
            'expired_at' => 'date',
            'quantity_received' => 'decimal:2',
            'quantity_available' => 'decimal:2',
            'purchase_cost' => 'decimal:2',
            'is_active' => 'boolean',
            'quarantined_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function dispenseBatches(): HasMany
    {
        return $this->hasMany(PrescriptionDispenseBatch::class)
            ->orderByDesc('created_at');
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class)
            ->orderByDesc('created_at');
    }

    public function purchaseReturnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class)
            ->orderByDesc('created_at');
    }

    public function stockAdjustmentItems(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class)
            ->orderByDesc('created_at');
    }

    public function stockOpnameItems(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class)
            ->orderByDesc('created_at');
    }

    public function isExpired(): bool
    {
        return $this->expired_at !== null
            && $this->expired_at->copy()->startOfDay()->lt(now()->startOfDay());
    }

    public function isQuarantined(): bool
    {
        return $this->quarantined_at !== null;
    }
}
