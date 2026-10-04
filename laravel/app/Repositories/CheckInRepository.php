<?php

namespace App\Repositories;

use App\Models\CheckIn;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckInRepository
{
    /** @return Collection<int, CheckIn> */
    public function staffHistory(): Collection
    {
        return CheckIn::query()
            ->with(['user', 'membership.gymPackage', 'closedBy'])
            ->latest('checked_in_at')
            ->limit(50)
            ->get();
    }

    /** @return HasMany<CheckIn, User> */
    public function forCustomer(User $customer): HasMany
    {
        return $customer->checkIns()->with(['membership.gymPackage'])->latest('checked_in_at');
    }

    public function findCustomerByIdentifier(string $identifier): ?User
    {
        $customerQuery = User::query()->where('role', 'customer');

        if (ctype_digit($identifier)) {
            $customerQuery->where(function ($query) use ($identifier): void {
                $query->whereKey((int) $identifier)->orWhere('phone', $identifier);
            });
        } else {
            $customerQuery->where('phone', $identifier);
        }

        return $customerQuery->lockForUpdate()->first();
    }

    public function hasOpenVisit(User $customer): bool
    {
        return CheckIn::query()
            ->where('user_id', $customer->id)
            ->whereNull('checked_out_at')
            ->exists();
    }

    public function openCount(): int
    {
        return CheckIn::query()->whereNull('checked_out_at')->count();
    }

    public function lockForUpdate(int $visitId): CheckIn
    {
        return CheckIn::query()->lockForUpdate()->findOrFail($visitId);
    }

    public function create(User $customer, Membership $membership): CheckIn
    {
        return CheckIn::query()->create([
            'user_id' => $customer->id,
            'membership_id' => $membership->id,
            'checked_in_at' => now(config('app.timezone')),
        ]);
    }
}
