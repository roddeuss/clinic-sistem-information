<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_opname_id',
        'medicine_batch_id',
        'medicine_id',
        'system_quantity_snapshot',
        'counted_quantity',
        'variance_quantity',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'system_quantity_snapshot' => 'decimal:2',
            'counted_quantity' => 'decimal:2',
            'variance_quantity' => 'decimal:2',
        ];
    }

    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
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
