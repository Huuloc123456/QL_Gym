<?php

namespace App\Policies;

use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MembershipOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function view(User $user, MembershipOrder $order): Response
    {
        if ($user->role === 'customer' && $order->user_id === $user->id) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function requestCancellation(User $user, MembershipOrder $order): Response
    {
        if (
            $user->role === 'customer'
            && $order->user_id === $user->id
            && $order->status === MembershipOrder::STATUS_PENDING
            && $order->cancellation_requested_at === null
        ) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    public function recordPayment(User $user, MembershipOrder $order): bool
    {
        return $this->isStaff($user);
    }

    public function confirm(User $user, MembershipOrder $order): bool
    {
        return $this->isStaff($user);
    }

    public function cancel(User $user, MembershipOrder $order): bool
    {
        return $this->isStaff($user);
    }

    private function isStaff(User $user): bool
    {
        return in_array($user->role, ['employee', 'manager'], true);
    }
}
