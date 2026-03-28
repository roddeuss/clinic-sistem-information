<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_adjustment_id',
        'medicine_batch_id',
        'medicine_id',
        'quantity_before',
        'quantity_delta',
        'quantity_after',
        'unit_cost_snapshot',
        'reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_before' => 'decimal:2',
            'quantity_delta' => 'decimal:2',
            'quantity_after' => 'decimal:2',
            'unit_cost_snapshot' => 'decimal:2',
        ];
    }

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function medicineBatch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
