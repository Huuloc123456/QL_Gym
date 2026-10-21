<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'username',
    'email',
    'phone',
    'birth_date',
    'gender',
    'role',
    'password',
    'can_manage_trial_requests',
    'can_manage_orders',
    'can_manage_check_ins',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'sql_customer_id' => 'integer',
            'sql_employee_id' => 'integer',
            'can_manage_trial_requests' => 'boolean',
            'can_manage_orders' => 'boolean',
            'can_manage_check_ins' => 'boolean',
        ];
    }

    public function canManageTrialRequests(): bool
    {
        return $this->role === 'manager'
            || ($this->role === 'employee' && $this->can_manage_trial_requests);
    }

    public function canManageOrders(): bool
    {
        return $this->role === 'manager'
            || ($this->role === 'employee' && $this->can_manage_orders);
    }

    public function canManageCheckIns(): bool
    {
        return $this->role === 'manager'
            || ($this->role === 'employee' && $this->can_manage_check_ins);
    }

    public function membershipOrders(): HasMany
    {
        return $this->hasMany(MembershipOrder::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
