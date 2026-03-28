<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRecordDiagnosis extends Model
{
    use HasFactory;

    protected $fillable = [
        'medical_record_id',
        'icd10_code_id',
        'diagnosis_type',
        'sort_order',
    ];

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function icd10Code(): BelongsTo
    {
        return $this->belongsTo(Icd10Code::class);
    }
}
