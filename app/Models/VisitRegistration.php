<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VisitRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'patient_branch_record_id',
        'branch_id',
        'counter_id',
        'section_id',
        'doctor_id',
        'doctor_schedule_id',
        'visit_date',
        'visit_type',
        'registration_status',
        'care_stage',
        'vital_status',
        'booking_code',
        'slot_start_time',
        'slot_end_time',
        'notes',
        'checked_in_at',
        'queued_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'checked_in_at' => 'datetime',
            'queued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
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

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function doctorSchedule(): BelongsTo
    {
        return $this->belongsTo(DoctorSchedule::class);
    }

    public function queueTicket(): HasOne
    {
        return $this->hasOne(QueueTicket::class);
    }

    public function vitalSignRecords(): HasMany
    {
        return $this->hasMany(VitalSignRecord::class)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id');
    }

    public function latestVitalSign(): HasOne
    {
        return $this->hasOne(VitalSignRecord::class)->ofMany([
            'recorded_at' => 'max',
            'id' => 'max',
        ]);
    }

    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class);
    }

    public function doctorAssignments(): HasMany
    {
        return $this->hasMany(VisitDoctorAssignment::class)
            ->orderByDesc('assigned_at')
            ->orderByDesc('id');
    }

    public function prescription(): HasOne
    {
        return $this->hasOne(Prescription::class);
    }

    public function visitProcedures(): HasMany
    {
        return $this->hasMany(VisitProcedure::class)
            ->orderByDesc('ordered_at')
            ->orderByDesc('id');
    }

    public function visitMedicalServices(): HasMany
    {
        return $this->hasMany(VisitMedicalService::class)
            ->orderByDesc('ordered_at')
            ->orderByDesc('id');
    }

    public function laboratoryOrders(): HasMany
    {
        return $this->hasMany(LaboratoryOrder::class)
            ->orderByDesc('ordered_at')
            ->orderByDesc('id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function referrals(): HasMany
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

    public function scopeSearchForManagement(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $searchQuery) use ($search): void {
            $searchQuery
                ->where('booking_code', 'like', '%' . $search . '%')
                ->orWhere('registration_status', 'like', '%' . $search . '%')
                ->orWhereHas('patient', function (Builder $patientQuery) use ($search): void {
                    $patientQuery
                        ->where('full_name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%');
                })
                ->orWhereHas('patientBranchRecord', function (Builder $recordQuery) use ($search): void {
                    $recordQuery->where('medical_record_no', 'like', '%' . $search . '%');
                })
                ->orWhereHas('doctor', function (Builder $doctorQuery) use ($search): void {
                    $doctorQuery->where('full_name', 'like', '%' . $search . '%');
                });
        });
    }

    public function scopeFilterDate(Builder $query, string $date): void
    {
        if ($date === '') {
            return;
        }

        $query->whereDate('visit_date', $date);
    }

    public function scopeFilterStatus(Builder $query, string $status): void
    {
        if ($status === '') {
            return;
        }

        $query->where('registration_status', $status);
    }

    public function scopeFilterType(Builder $query, string $type): void
    {
        if ($type === '') {
            return;
        }

        $query->where('visit_type', $type);
    }

    public function scopeFilterSection(Builder $query, string $sectionId): void
    {
        if ($sectionId === '') {
            return;
        }

        $query->where('section_id', $sectionId);
    }

    public function scopeOrderByAllowed(Builder $query, string $sortBy, string $direction, array $allowedSorts): void
    {
        $column = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'visit_date';
        $sortDirection = $direction === 'asc' ? 'asc' : 'desc';

        if ($column === 'checked_in_at') {
            $query
                ->orderByRaw('case when checked_in_at is null then 1 else 0 end')
                ->orderBy('checked_in_at', $sortDirection);
        } else {
            $query->orderBy($column, $sortDirection);
        }

        if ($column !== 'visit_date') {
            $query->orderByDesc('visit_date');
        }

        $query->orderByDesc('id');
    }
}
