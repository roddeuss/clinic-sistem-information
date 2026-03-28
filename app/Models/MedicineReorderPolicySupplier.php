<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicineReorderPolicySupplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'medicine_reorder_policy_id',
        'supplier_id',
        'priority',
        'is_primary',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(MedicineReorderPolicy::class, 'medicine_reorder_policy_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
