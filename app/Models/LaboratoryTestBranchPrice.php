<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaboratoryTestBranchPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'laboratory_test_id',
        'internal_price',
        'external_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'internal_price' => 'decimal:2',
            'external_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function laboratoryTest(): BelongsTo
    {
        return $this->belongsTo(LaboratoryTest::class);
    }
}
