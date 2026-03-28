<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicine extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'product_category_id',
        'name',
        'generic_name',
        'active_ingredients',
        'allergy_keywords',
        'dosage_form',
        'therapeutic_class',
        'strength',
        'base_unit',
        'description',
        'contraindication_notes',
        'is_compoundable',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_compoundable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Medicine $medicine): void {
            $medicine->ensureDefaultUnit();
        });
    }

    public function branchPrices(): HasMany
    {
        return $this->hasMany(MedicineBranchPrice::class)
            ->orderBy('branch_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(MedicineUnit::class)
            ->orderBy('sort_order')
            ->orderBy('conversion_factor')
            ->orderBy('id');
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(MedicineBatch::class)
            ->orderBy('expired_at')
            ->orderBy('received_at');
    }

    public function prescriptionItems(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class)
            ->orderByDesc('created_at');
    }

    public function compoundIngredients(): HasMany
    {
        return $this->hasMany(PrescriptionCompoundIngredient::class)
            ->orderByDesc('created_at');
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)
            ->orderByDesc('created_at');
    }

    public function reorderPolicies(): HasMany
    {
        return $this->hasMany(MedicineReorderPolicy::class)
            ->orderBy('branch_id');
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class)
            ->orderByDesc('created_at');
    }

    public function purchaseReturnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class)
            ->orderByDesc('created_at');
    }

    public function stockAdjustmentItems(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class)
            ->orderByDesc('created_at');
    }

    public function stockOpnameItems(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class)
            ->orderByDesc('created_at');
    }

    public function ensureDefaultUnit(): void
    {
        $label = strtoupper(trim((string) ($this->base_unit ?: 'UNIT')));

        $this->units()->updateOrCreate(
            ['label' => $label],
            [
                'conversion_factor' => 1,
                'is_base' => true,
                'allow_purchase' => true,
                'allow_dispense' => true,
                'sort_order' => 10,
                'is_active' => true,
            ],
        );
    }
}
