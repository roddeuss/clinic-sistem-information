<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'branch_id',
        'auditable_type',
        'auditable_id',
        'module',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'before_data',
        'after_data',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'before_data' => 'array',
            'after_data' => 'array',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
