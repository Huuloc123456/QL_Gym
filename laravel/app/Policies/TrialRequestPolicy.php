<?php

namespace App\Policies;

use App\Models\TrialRequest;
use App\Models\User;

class TrialRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function markContacted(User $user, TrialRequest $trialRequest): bool
    {
        return $this->isStaff($user) && $trialRequest->status === 'received';
    }

    private function isStaff(User $user): bool
    {
        return in_array($user->role, ['employee', 'manager'], true);
    }
}
