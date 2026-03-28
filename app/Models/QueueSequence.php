<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'queue_date',
        'last_number',
    ];

    protected function casts(): array
    {
        return [
            'queue_date' => 'date',
            'last_number' => 'integer',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
