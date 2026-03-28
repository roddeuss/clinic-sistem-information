<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'employee_number',
        'full_name',
        'email',
        'phone',
        'employment_type',
        'status',
    ];

    /**
     * Get the user account attached to the employee.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
