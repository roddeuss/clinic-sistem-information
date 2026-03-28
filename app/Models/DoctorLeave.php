<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorLeave extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'branch_id',
        'leave_date',
        'leave_type',
        'start_time',
        'end_time',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'leave_date' => 'date',
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
}
