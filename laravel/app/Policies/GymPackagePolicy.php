<?php

namespace App\Policies;

use App\Models\GymPackage;
use App\Models\User;

class GymPackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'manager';
    }

    public function create(User $user): bool
    {
        return $user->role === 'manager';
    }

    public function update(User $user, GymPackage $gymPackage): bool
    {
        return $user->role === 'manager';
    }

    public function toggleActive(User $user, GymPackage $gymPackage): bool
    {
        return $user->role === 'manager';
    }
}
