<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrescriptionDispense extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_item_id',
        'visit_registration_id',
        'branch_id',
        'dispensed_by_user_id',
        'quantity_dispensed',
        'unit_price',
        'subtotal',
        'dispensed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_dispensed' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'dispensed_at' => 'datetime',
        ];
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function visitRegistration(): BelongsTo
    {
        return $this->belongsTo(VisitRegistration::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function dispensedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by_user_id');
    }

    public function batchUsages(): HasMany
    {
        return $this->hasMany(PrescriptionDispenseBatch::class)
            ->orderBy('id');
    }
}
