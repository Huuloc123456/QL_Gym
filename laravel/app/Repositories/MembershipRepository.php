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
            ->select('memberships.*')
            ->selectSub(DB::table('DANGKYGOITAP')->select('TRANGTHAI')
                ->whereColumn('DANGKYGOITAP.MADKGT', 'memberships.sql_membership_id')
                ->limit(1), 'sql_membership_status')
            ->orderByDesc('starts_on')
            ->orderByDesc('id');

        if ($section === 'active') {
            $this->sourceEligible($query);
            $query->whereDate('starts_on', '<=', $today)->whereDate('expires_on', '>', $today);
        } elseif ($section === 'upcoming') {
            $this->sourceEligible($query);
            $query->whereDate('starts_on', '>', $today)->reorder()->orderBy('starts_on')->orderBy('id');
        } else {
            $query->where(function (Builder $history) use ($today): void {
                $history->whereDate('expires_on', '<=', $today)
                    ->orWhereIn('sql_membership_id', DB::table('DANGKYGOITAP')
                        ->select('MADKGT')->whereIn('TRANGTHAI', ['Hết hạn', 'Đã hủy']));
            })->reorder()->orderByDesc('expires_on')->orderByDesc('id');
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

    private function sourceEligible(Builder|HasMany $query): void
    {
        if (Schema::hasTable('DANGKYGOITAP')) {
            $query->where(function (Builder $query): void {
                $query->whereNull('sql_membership_id')
                    ->orWhereIn('sql_membership_id', DB::table('DANGKYGOITAP')
                        ->select('MADKGT')->where('TRANGTHAI', 'Còn hiệu lực'));
            });
        }
    }
}
