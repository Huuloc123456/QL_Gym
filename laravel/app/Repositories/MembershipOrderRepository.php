<?php

namespace App\Repositories;

use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipOrderRepository
{
    /** @return HasMany<MembershipOrder, User> */
    public function forCustomer(User $customer): HasMany
    {
        return $customer->membershipOrders()
            ->with(['gymPackage', 'membership', 'payments'])
            ->withSum('payments', 'amount')
            ->latest();
    }

    public function findForCustomer(User $customer, int $orderId): MembershipOrder
    {
        return $customer->membershipOrders()
            ->with(['gymPackage', 'membership', 'payments.receiver'])
            ->findOrFail($orderId);
    }

    /** @return Builder<MembershipOrder> */
    public function forStaff(): Builder
    {
        return MembershipOrder::query()
            ->with(['user', 'gymPackage', 'payments.receiver', 'membership'])
            ->withSum('payments', 'amount')
            ->latest();
    }

    /** @return Collection<int, MembershipOrder> */
    public function recent(int $limit): Collection
    {
        return MembershipOrder::query()
            ->with(['user', 'gymPackage'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function pendingCount(): int
    {
        return MembershipOrder::query()->where('status', MembershipOrder::STATUS_PENDING)->count();
    }

    public function paidRevenue(): int
    {
        return (int) MembershipOrder::query()
            ->where('status', MembershipOrder::STATUS_PAID)
            ->sum('amount');
    }

    /** @param array<string, mixed> $attributes */
    public function createPending(array $attributes): MembershipOrder
    {
        return MembershipOrder::query()->create($attributes);
    }

    public function lockForUpdate(int $orderId): MembershipOrder
    {
        return MembershipOrder::query()->lockForUpdate()->findOrFail($orderId);
    }
}
