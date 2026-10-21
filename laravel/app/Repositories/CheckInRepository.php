<?php

namespace App\Repositories;

use App\Models\CheckIn;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;

class CheckInRepository
{
    /** @return Collection<int, CheckIn> */
    public function openVisits(): Collection
    {
        return CheckIn::query()
            ->with(['user', 'membership.gymPackage'])
            ->whereNull('checked_out_at')
            ->latest('checked_in_at')
            ->get();
    }

    /** @param array{status: string, q?: string|null, from_date?: string|null, to_date?: string|null}
     * @return LengthAwarePaginator<int, CheckIn>
     */
    public function staffHistory(array $filters): LengthAwarePaginator
    {
        $query = CheckIn::query()
            ->with(['user', 'membership.gymPackage', 'closedBy'])
            ->orderByDesc('checked_in_at')
            ->orderByDesc('id');

        if ($filters['status'] === 'open') {
            $query->whereNull('checked_out_at');
        } elseif ($filters['status'] === 'history') {
            $query->whereNotNull('checked_out_at');
        }

        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            $query->whereHas('user', function (Builder $userQuery) use ($search): void {
                if (ctype_digit($search) && strlen($search) < 9) {
                    $userQuery->whereKey((int) $search)->orWhere('phone', 'like', '%'.$search.'%');

                    return;
                }

                $userQuery->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('checked_in_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('checked_in_at', '<=', $filters['to_date']);
        }

        return $query->paginate(20)->withQueryString();
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
            $sqlCustomer = (clone $customerQuery)->where('sql_customer_id', (int) $identifier)->first();
            if ($sqlCustomer) {
                return $sqlCustomer;
            }

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
