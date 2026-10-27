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
        return $this->isStaff($user) && in_array($trialRequest->status, ['received', 'pending'], true) && $trialRequest->handled_at === null;
    }

    public function approve(User $user, TrialRequest $trialRequest): bool
    {
        return $this->isStaff($user);
    }

    public function reschedule(User $user, TrialRequest $trialRequest): bool
    {
        return $this->isStaff($user)
            && $trialRequest->status === 'approved'
            && $trialRequest->entitlement?->revoked_at === null
            && $trialRequest->entitlement !== null
            && ! $trialRequest->entitlement->checkIns()->exists();
    }

    public function revoke(User $user, TrialRequest $trialRequest): bool
    {
        return $this->isStaff($user)
            && $trialRequest->status === 'approved'
            && $trialRequest->entitlement?->revoked_at === null
            && $trialRequest->entitlement !== null;
    }

    private function isStaff(User $user): bool
    {
        return $user->canManageTrialRequests();
    }
}
