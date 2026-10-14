<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'manager';
    }

    public function create(User $user): bool
    {
        return $user->role === 'manager';
    }

    public function update(User $user, User $employee): bool
    {
        return $user->role === 'manager' && $employee->role === 'employee';
    }
}
