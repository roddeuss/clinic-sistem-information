<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'phone',
        'email',
        'npwp',
        'payment_term_days',
        'address',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'payment_term_days' => 'integer',
        ];
    }

    public function medicineBatches(): HasMany
    {
        return $this->hasMany(MedicineBatch::class)
            ->orderByDesc('received_at')
            ->orderByDesc('created_at');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class)
            ->orderByDesc('order_date')
            ->orderByDesc('created_at');
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class)
            ->orderByDesc('received_at')
            ->orderByDesc('created_at');
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class)
            ->orderByDesc('return_date')
            ->orderByDesc('created_at');
    }

    public function reorderPolicyLinks(): HasMany
    {
        return $this->hasMany(MedicineReorderPolicySupplier::class)
            ->orderByDesc('is_primary')
            ->orderBy('priority');
    }
}
