<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedureBranchPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'procedure_master_id',
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

    public function procedureMaster(): BelongsTo
    {
        return $this->belongsTo(ProcedureMaster::class);
    }
}
