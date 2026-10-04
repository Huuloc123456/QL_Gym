<?php

namespace App\Policies;

use App\Models\CheckIn;
use App\Models\User;

class CheckInPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function checkIn(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function checkOut(User $user, CheckIn $visit): bool
    {
        return $this->isStaff($user) && $visit->checked_out_at === null;
    }

    public function manualClose(User $user, CheckIn $visit): bool
    {
        return $this->isStaff($user) && $visit->checked_out_at === null;
    }

    private function isStaff(User $user): bool
    {
        return in_array($user->role, ['employee', 'manager'], true);
    }
}
