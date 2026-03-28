<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'payment_method_id',
        'cashier_shift_id',
        'amount',
        'payment_date',
        'paid_by_user_id',
        'payment_reference',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function cashierShift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }
}
