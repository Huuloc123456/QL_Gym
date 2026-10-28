<?php

namespace App\Repositories;

use App\Models\Membership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MembershipRepository
{
    /** @return HasMany<Membership, User> */
    public function forCustomer(User $customer): HasMany
    {
        return $customer->memberships()->with(['gymPackage', 'order'])->latest('starts_on');
    }

    /** @return LengthAwarePaginator<int, Membership> */
    public function paginateForCustomerSection(User $customer, string $section, string $today): LengthAwarePaginator
    {
        $query = $customer->memberships()
            ->with(['gymPackage', 'order'])
            ->orderByDesc('starts_on')
            ->orderByDesc('id');
        $this->sourceEligible($query, $section === 'expired');

        if ($section === 'active') {
            $query->whereDate('starts_on', '<=', $today)->whereDate('expires_on', '>', $today);
        } elseif ($section === 'upcoming') {
            $query->whereDate('starts_on', '>', $today)->reorder()->orderBy('starts_on')->orderBy('id');
        } else {
            $query->whereDate('expires_on', '<=', $today)->reorder()->orderByDesc('expires_on')->orderByDesc('id');
        }

        $pageName = $section === 'expired' ? 'history_page' : $section.'_page';

        return $query->paginate(10, ['*'], $pageName)->withQueryString();
    }

    public function latestFutureExpiry(User $customer, CarbonImmutable $today): ?string
    {
        $query = $customer->memberships()->whereDate('expires_on', '>', $today->toDateString());
        $this->sourceEligible($query);
        $date = $query->max('expires_on');

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
        $this->sourceEligible($query);

        return $query->first();
    }

    public function periodOverlaps(User $customer, CarbonImmutable $startsOn, CarbonImmutable $expiresOn): bool
    {
        $query = Membership::query()
            ->where('user_id', $customer->id)
            ->whereDate('starts_on', '<', $expiresOn->toDateString())
            ->whereDate('expires_on', '>', $startsOn->toDateString())
            ->lockForUpdate();
        $this->sourceEligible($query);

        return $query->exists();
    }

    public function activeCountOn(string $date): int
    {
        $query = Membership::query()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('expires_on', '>', $date);
        $this->sourceEligible($query);

        return $query->count();
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Membership
    {
        return Membership::query()->create($attributes);
    }

    private function sourceEligible(Builder|HasMany $query, bool $includeExpired = false): void
    {
        if (Schema::hasTable('DANGKYGOITAP')) {
            $query->where(function (Builder $query) use ($includeExpired): void {
                $query->whereNull('sql_membership_id')
                    ->orWhereIn('sql_membership_id', DB::table('DANGKYGOITAP')
                        ->select('MADKGT')->whereIn('TRANGTHAI', $includeExpired ? ['Còn hiệu lực', 'Hết hạn'] : ['Còn hiệu lực']));
            });
        }
    }
}
