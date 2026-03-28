<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaboratoryTestParameter extends Model
{
    use HasFactory;

    protected $fillable = [
        'laboratory_test_id',
        'code',
        'name',
        'unit',
        'reference_range',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function laboratoryTest(): BelongsTo
    {
        return $this->belongsTo(LaboratoryTest::class);
    }
}
