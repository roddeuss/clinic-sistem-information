<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrugInteractionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'left_operand_type',
        'left_operand_value',
        'right_operand_type',
        'right_operand_value',
        'severity',
        'title',
        'clinical_effect',
        'management_advice',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(PrescriptionInteractionOverride::class)
            ->orderByDesc('overridden_at');
    }
}
