<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'medicine_id',
        'medicine_unit_id',
        'unit_label',
        'conversion_factor',
        'quantity_ordered',
        'quantity_ordered_base',
        'unit_cost',
        'unit_cost_base',
        'subtotal',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:4',
            'quantity_ordered' => 'decimal:2',
            'quantity_ordered_base' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'unit_cost_base' => 'decimal:4',
            'subtotal' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function medicineUnit(): BelongsTo
    {
        return $this->belongsTo(MedicineUnit::class);
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class)
            ->orderBy('id');
    }
}
