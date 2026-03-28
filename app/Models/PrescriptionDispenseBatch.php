<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionDispenseBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_dispense_id',
        'medicine_batch_id',
        'quantity_used',
        'purchase_cost_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity_used' => 'decimal:2',
            'purchase_cost_snapshot' => 'decimal:2',
        ];
    }

    public function prescriptionDispense(): BelongsTo
    {
        return $this->belongsTo(PrescriptionDispense::class);
    }

    public function medicineBatch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class);
    }
}
