<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'title_prefix',
        'title_suffix',
        'specialization',
        'consultation_fee',
        'str_number',
        'str_expired_at',
        'sip_number',
        'sip_expired_at',
        'phone',
        'email',
        'address',
        'signature_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'consultation_fee' => 'decimal:2',
            'str_expired_at' => 'date',
            'sip_expired_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class)
            ->withTimestamps()
            ->orderBy('sections.name');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class)
            ->orderBy('day_of_week')
            ->orderBy('start_time');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(DoctorLeave::class)
            ->orderByDesc('leave_date')
            ->orderBy('start_time');
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

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class)
            ->orderByDesc('updated_at');
    }

    public function visitDoctorAssignments(): HasMany
    {
        return $this->hasMany(VisitDoctorAssignment::class)
            ->orderByDesc('assigned_at');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class)
            ->orderByDesc('updated_at');
    }

    public function orderedVisitProcedures(): HasMany
    {
        return $this->hasMany(VisitProcedure::class, 'ordered_by_doctor_id')
            ->orderByDesc('ordered_at');
    }

    public function laboratoryOrders(): HasMany
    {
        return $this->hasMany(LaboratoryOrder::class, 'ordered_by_doctor_id')
            ->orderByDesc('ordered_at');
    }

    public function patientReferrals(): HasMany
    {
        return $this->hasMany(PatientReferral::class)
            ->orderByDesc('issued_at')
            ->orderByDesc('created_at');
    }

    public function doctorLetters(): HasMany
    {
        return $this->hasMany(DoctorLetter::class)
            ->orderByDesc('issued_at')
            ->orderByDesc('created_at');
    }

    public function displayName(): string
    {
        $baseName = collect([
            $this->title_prefix,
            $this->full_name,
        ])->filter()->implode(' ');

        return $this->title_suffix
            ? trim($baseName . ', ' . $this->title_suffix)
            : $baseName;
    }
}
