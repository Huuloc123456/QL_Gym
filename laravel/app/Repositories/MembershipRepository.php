<?php

namespace App\Repositories;

use App\Models\Membership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipRepository
{
    /** @return HasMany<Membership, User> */
    public function forCustomer(User $customer): HasMany
    {
        return $customer->memberships()->with(['gymPackage', 'order'])->latest('starts_on');
    }

    public function latestFutureExpiry(User $customer, CarbonImmutable $today): ?string
    {
        $date = $customer->memberships()
            ->whereDate('expires_on', '>', $today->toDateString())
            ->max('expires_on');

        return $date === null ? null : (string) $date;
    }

    public function activeFor(User $customer, string $date, bool $lockForUpdate = false): ?Membership
    {
        $query = Membership::query()
            ->where('user_id', $customer->id)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('expires_on', '>', $date)
            ->orderByDesc('starts_on');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function periodOverlaps(User $customer, CarbonImmutable $startsOn, CarbonImmutable $expiresOn): bool
    {
        return Membership::query()
            ->where('user_id', $customer->id)
            ->whereDate('starts_on', '<', $expiresOn->toDateString())
            ->whereDate('expires_on', '>', $startsOn->toDateString())
            ->lockForUpdate()
            ->exists();
    }

    public function activeCountOn(string $date): int
    {
        return Membership::query()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('expires_on', '>', $date)
            ->count();
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Membership
    {
        return Membership::query()->create($attributes);
    }
}
