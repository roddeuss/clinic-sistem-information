<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the linked employee profile for this user.
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function calledQueueTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class, 'called_by_user_id')
            ->orderByDesc('called_at');
    }

    public function recordedVitalSigns(): HasMany
    {
        return $this->hasMany(VitalSignRecord::class, 'recorded_by_user_id')
            ->orderByDesc('recorded_at');
    }

    public function finalizedMedicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class, 'finalized_by_user_id')
            ->orderByDesc('finalized_at');
    }

    public function dispensedPrescriptions(): HasMany
    {
        return $this->hasMany(PrescriptionDispense::class, 'dispensed_by_user_id')
            ->orderByDesc('dispensed_at');
    }

    public function performedProcedures(): HasMany
    {
        return $this->hasMany(VisitProcedure::class, 'performed_by_user_id')
            ->orderByDesc('completed_at')
            ->orderByDesc('ordered_at');
    }

    public function paidInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'paid_by_user_id')
            ->orderByDesc('paid_at');
    }

    public function primaryRoleName(): ?string
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles
                ->pluck('name')
                ->sort()
                ->first();
        }

        return $this->getRoleNames()
            ->sort()
            ->first();
    }

    public function scopeSearchForManagement(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function (Builder $searchQuery) use ($search): void {
            $searchQuery
                ->where('name', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%')
                ->orWhereHas('employee', function (Builder $employeeQuery) use ($search): void {
                    $employeeQuery->where('employee_number', 'like', '%' . $search . '%');
                });
        });
    }

    public function scopeFilterRole(Builder $query, string $role): void
    {
        if ($role === '') {
            return;
        }

        $query->whereHas('roles', function (Builder $roleQuery) use ($role): void {
            $roleQuery->where('name', $role);
        });
    }

    public function scopeFilterStatus(Builder $query, string $status): void
    {
        if ($status === '') {
            return;
        }

        $query->where('is_active', $status === 'active');
    }

    public function scopeOrderByAllowed(Builder $query, string $sortBy, string $direction, array $allowedSorts): void
    {
        $column = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'name';
        $sortDirection = $direction === 'desc' ? 'desc' : 'asc';

        if ($column === 'last_login_at') {
            $query
                ->orderByRaw('case when last_login_at is null then 1 else 0 end')
                ->orderBy('last_login_at', $sortDirection);
        } else {
            $query->orderBy($column, $sortDirection);
        }

        if ($column !== 'name') {
            $query->orderBy('name');
        }
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }
}
