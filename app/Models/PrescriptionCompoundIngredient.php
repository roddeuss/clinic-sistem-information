<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionCompoundIngredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_item_id',
        'medicine_id',
        'quantity_required',
        'unit',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_required' => 'decimal:2',
        ];
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
