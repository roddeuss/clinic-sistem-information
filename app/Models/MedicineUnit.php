<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicineUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'medicine_id',
        'label',
        'conversion_factor',
        'is_base',
        'allow_purchase',
        'allow_dispense',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:4',
            'is_base' => 'boolean',
            'allow_purchase' => 'boolean',
            'allow_dispense' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)
            ->orderByDesc('created_at');
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class)
            ->orderByDesc('created_at');
    }

    public function reorderPolicies(): HasMany
    {
        return $this->hasMany(MedicineReorderPolicy::class, 'preferred_purchase_unit_id')
            ->orderByDesc('updated_at');
    }
}
