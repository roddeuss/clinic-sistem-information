<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaboratoryResultEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'laboratory_order_id',
        'parameter_code',
        'parameter_name',
        'value',
        'unit',
        'reference_range',
        'result_flag',
        'notes',
        'sort_order',
    ];

    public function laboratoryOrder(): BelongsTo
    {
        return $this->belongsTo(LaboratoryOrder::class);
    }
}
