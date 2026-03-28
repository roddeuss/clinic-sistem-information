<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clinic extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'logo_path',
        'phone',
        'email',
        'address',
        'invoice_header',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
