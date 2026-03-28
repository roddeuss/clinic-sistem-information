<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'medicine_id',
        'medicine_unit_id',
        'medicine_batch_id',
        'unit_label',
        'conversion_factor',
        'batch_number',
        'expired_at',
        'quantity_received',
        'quantity_received_base',
        'unit_cost',
        'unit_cost_base',
        'subtotal',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:4',
            'expired_at' => 'date',
            'quantity_received' => 'decimal:2',
            'quantity_received_base' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'unit_cost_base' => 'decimal:4',
            'subtotal' => 'decimal:2',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function medicineUnit(): BelongsTo
    {
        return $this->belongsTo(MedicineUnit::class);
    }

    public function medicineBatch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class);
    }
}
