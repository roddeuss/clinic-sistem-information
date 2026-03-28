<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_registration_id',
        'patient_id',
        'patient_branch_record_id',
        'branch_id',
        'payment_method_id',
        'cashier_shift_id',
        'invoice_no',
        'payer_type',
        'payer_name',
        'payer_meta',
        'status',
        'subtotal',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'issued_at',
        'paid_at',
        'paid_by_user_id',
        'payment_reference',
        'voided_by_user_id',
        'voided_at',
        'void_reason',
        'printed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'payer_meta' => 'array',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }

    public function visitRegistration(): BelongsTo
    {
        return $this->belongsTo(VisitRegistration::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function patientBranchRecord(): BelongsTo
    {
        return $this->belongsTo(PatientBranchRecord::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)
            ->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)
            ->orderBy('payment_date')
            ->orderBy('id');
    }

    public function receivable(): HasOne
    {
        return $this->hasOne(Receivable::class);
    }
}
