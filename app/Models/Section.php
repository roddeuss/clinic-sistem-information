<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'type',
        'queue_prefix',
        'queue_number_padding',
        'allow_appointment',
        'allow_walk_in',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allow_appointment' => 'boolean',
            'allow_walk_in' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class)
            ->withTimestamps()
            ->orderBy('doctors.full_name');
    }

    public function doctorSchedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class)
            ->orderBy('day_of_week')
            ->orderBy('start_time');
    }

    public function visitRegistrations(): HasMany
    {
        return $this->hasMany(VisitRegistration::class)
            ->orderByDesc('visit_date')
            ->orderByDesc('created_at');
    }

    public function queueSequences(): HasMany
    {
        return $this->hasMany(QueueSequence::class)
            ->orderByDesc('queue_date');
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

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class)
            ->orderByDesc('updated_at');
    }

    public function visitProcedures(): HasMany
    {
        return $this->hasMany(VisitProcedure::class)
            ->orderByDesc('ordered_at');
    }

    public function laboratoryOrders(): HasMany
    {
        return $this->hasMany(LaboratoryOrder::class)
            ->orderByDesc('ordered_at');
    }
}
