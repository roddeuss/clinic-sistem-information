<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoctorSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'branch_id',
        'section_id',
        'day_of_week',
        'start_time',
        'end_time',
        'slot_duration_minutes',
        'max_patients',
        'room_label',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'slot_duration_minutes' => 'integer',
            'max_patients' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function visitRegistrations(): HasMany
    {
        return $this->hasMany(VisitRegistration::class)
            ->orderByDesc('visit_date')
            ->orderByDesc('created_at');
    }
}
