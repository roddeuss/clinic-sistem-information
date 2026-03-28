<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalServiceBranchPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'medical_service_id',
        'price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function medicalService(): BelongsTo
    {
        return $this->belongsTo(MedicalService::class);
    }
}
