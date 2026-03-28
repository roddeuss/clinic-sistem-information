<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicineReorderPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'medicine_id',
        'branch_id',
        'preferred_purchase_unit_id',
        'minimum_stock',
        'safety_stock',
        'reorder_point',
        'reorder_quantity',
        'lead_time_days',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'minimum_stock' => 'decimal:2',
            'safety_stock' => 'decimal:2',
            'reorder_point' => 'decimal:2',
            'reorder_quantity' => 'decimal:2',
            'lead_time_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function preferredPurchaseUnit(): BelongsTo
    {
        return $this->belongsTo(MedicineUnit::class, 'preferred_purchase_unit_id');
    }

    public function supplierPreferences(): HasMany
    {
        return $this->hasMany(MedicineReorderPolicySupplier::class)
            ->orderByDesc('is_primary')
            ->orderBy('priority')
            ->orderBy('id');
    }
}
