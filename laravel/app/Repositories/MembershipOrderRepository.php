<?php

namespace App\Repositories;

use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;

class MembershipOrderRepository
{
    /** @return HasMany<MembershipOrder, User> */
    public function forCustomer(User $customer): HasMany
    {
        return $customer->membershipOrders()
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('gym_fulfillments')->whereColumn('invoice_id', 'membership_orders.sql_invoice_id'))
            ->with(['gymPackage', 'membership', 'payments'])
            ->withSum('payments', 'amount')
            ->latest();
    }

    /** @return LengthAwarePaginator<int, MembershipOrder> */
    public function paginateForCustomer(User $customer, string $status): LengthAwarePaginator
    {
        $query = $customer->membershipOrders()
            ->with(['gymPackage', 'membership'])
            ->withSum('payments', 'amount')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (in_array($status, [MembershipOrder::STATUS_PENDING, MembershipOrder::STATUS_PAID, MembershipOrder::STATUS_CANCELLED], true)) {
            $query->where('status', $status);
        }

        return $query->paginate(20)->withQueryString();
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
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('gym_fulfillments')->whereColumn('invoice_id', 'membership_orders.sql_invoice_id'))
            ->withSum('payments', 'amount')
            ->latest();
    }

    /** @param array{status: string, q?: string|null, from_date?: string|null, to_date?: string|null} $filters
     * @return LengthAwarePaginator<int, MembershipOrder>
     */
    public function paginateForStaff(array $filters): LengthAwarePaginator
    {
        $query = $this->forStaff();
        $status = $filters['status'];

        if ($status === 'pending') {
            $query->where('status', MembershipOrder::STATUS_PENDING);
        } elseif ($status === 'history') {
            $query->whereIn('status', [MembershipOrder::STATUS_PAID, MembershipOrder::STATUS_CANCELLED]);
        } elseif (in_array($status, [MembershipOrder::STATUS_PENDING, MembershipOrder::STATUS_PAID, MembershipOrder::STATUS_CANCELLED], true)) {
            $query->where('status', $status);
        }

        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                if (ctype_digit($search) && strlen($search) < 9) {
                    $query->whereKey((int) $search);

                    return;
                }

                $query->whereHas('user', fn (Builder $userQuery) => $userQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%'));
            });
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        return $query->orderByDesc('id')->paginate(20)->withQueryString();
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
