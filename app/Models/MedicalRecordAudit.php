<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRecordAudit extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'medical_record_id',
        'action',
        'notes',
        'snapshot',
        'performed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
