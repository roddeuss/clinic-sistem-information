<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'name',
        'code',
        'phone',
        'address',
        'opening_time',
        'closing_time',
        'queue_prefix',
        'queue_number_padding',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('sort_order')->orderBy('name');
    }

    public function counters(): HasMany
    {
        return $this->hasMany(Counter::class)->orderBy('sort_order')->orderBy('name');
    }

    public function doctorSchedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class)
            ->orderBy('day_of_week')
            ->orderBy('start_time');
    }

    public function doctorLeaves(): HasMany
    {
        return $this->hasMany(DoctorLeave::class)
            ->orderByDesc('leave_date')
            ->orderBy('start_time');
    }

    public function patientBranchRecords(): HasMany
    {
        return $this->hasMany(PatientBranchRecord::class)
            ->orderByDesc('created_at');
    }

    public function visitRegistrations(): HasMany
    {
        return $this->hasMany(VisitRegistration::class)
            ->orderByDesc('visit_date')
            ->orderByDesc('created_at');
    }

    public function queueTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class)
            ->orderByDesc('queue_date')
            ->orderByDesc('created_at');
    }

    public function vitalSignRecords(): HasMany
    {
        return $this->hasMany(VitalSignRecord::class)
            ->orderByDesc('recorded_at');
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class)
            ->orderByDesc('updated_at');
    }

    public function medicineBranchPrices(): HasMany
    {
        return $this->hasMany(MedicineBranchPrice::class)
            ->orderByDesc('updated_at');
    }

    public function medicineBatches(): HasMany
    {
        return $this->hasMany(MedicineBatch::class)
            ->orderBy('expired_at')
            ->orderBy('received_at');
    }

    public function medicineReorderPolicies(): HasMany
    {
        return $this->hasMany(MedicineReorderPolicy::class)
            ->orderByDesc('is_active')
            ->orderBy('id');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class)
            ->orderByDesc('updated_at');
    }

    public function procedureBranchPrices(): HasMany
    {
        return $this->hasMany(ProcedureBranchPrice::class)
            ->orderByDesc('updated_at');
    }

    public function visitProcedures(): HasMany
    {
        return $this->hasMany(VisitProcedure::class)
            ->orderByDesc('ordered_at');
    }

    public function laboratoryTestBranchPrices(): HasMany
    {
        return $this->hasMany(LaboratoryTestBranchPrice::class)
            ->orderByDesc('updated_at');
    }

    public function laboratoryOrders(): HasMany
    {
        return $this->hasMany(LaboratoryOrder::class)
            ->orderByDesc('ordered_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)
            ->orderByDesc('issued_at')
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

    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class)
            ->orderByDesc('adjustment_date')
            ->orderByDesc('created_at');
    }

    public function stockOpnames(): HasMany
    {
        return $this->hasMany(StockOpname::class)
            ->orderByDesc('opname_date')
            ->orderByDesc('created_at');
    }
}
