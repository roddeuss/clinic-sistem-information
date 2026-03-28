<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemBackup extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'backup_no',
        'backup_type',
        'dump_format',
        'status',
        'disk',
        'file_path',
        'file_name',
        'file_size_bytes',
        'notes',
        'failure_reason',
        'created_by_user_id',
        'restored_by_user_id',
        'started_at',
        'completed_at',
        'restored_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'restored_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by_user_id');
    }
}
