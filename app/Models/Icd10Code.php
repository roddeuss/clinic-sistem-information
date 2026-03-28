<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Icd10Code extends Model
{
    use HasFactory;

    protected $fillable = [
        'chapter_code',
        'code',
        'name_en',
        'name_id',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(MedicalRecordDiagnosis::class)
            ->orderBy('diagnosis_type')
            ->orderBy('sort_order');
    }

    public function displayLabel(): string
    {
        return sprintf('%s - %s', $this->code, $this->name_en);
    }
}
