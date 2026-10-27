<?php

namespace App\Services;

use App\Models\CheckIn;
use App\Models\TrialEntitlement;
use App\Models\User;
use App\Repositories\CheckInRepository;
use App\Repositories\MembershipRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInService
{
    public function __construct(
        private CheckInRepository $checkIns,
        private MembershipRepository $memberships,
        private GymIdentityService $identities,
        private OriginalGymSql $sql,
    ) {}

    public function checkIn(string $identifier): CheckIn
    {
        return $this->sql->transaction(function () use ($identifier): CheckIn {
            $customer = $this->checkIns->findCustomerByIdentifier($identifier);

            if (! $customer) {
                throw ValidationException::withMessages(['member' => 'Không tìm thấy hội viên theo mã hoặc số điện thoại.']);
            }

            if (! $customer->is_active) {
                throw ValidationException::withMessages(['member' => 'Tài khoản hội viên đã bị khóa.']);
            }

            if ($this->checkIns->hasOpenVisit($customer)) {
                throw ValidationException::withMessages(['member' => 'Hội viên còn lượt tập chưa ghi giờ ra.']);
            }

            $today = now(config('app.timezone'))->toDateString();
            $membership = $this->memberships->activeFor($customer, $today, lockForUpdate: true);

            $trial = TrialEntitlement::query()->where('user_id', $customer->id)->whereNull('revoked_at')
                ->where('starts_at', '<=', now())->where('ends_at', '>', now())->lockForUpdate()->first();

            $sourceMembership = DB::table('DANGKYGOITAP')
                ->select('MADKGT')
                ->where('MAKH', $customer->sql_customer_id)
                ->where('TRANGTHAI', 'Còn hiệu lực')
                ->where('NGAYBATDAU', '<=', now())
                ->where('NGAYKETTHUC', '>=', now())
                ->orderByDesc('NGAYKETTHUC')
                ->lockForUpdate()
                ->first();
            if (! $membership && ! $sourceMembership && ! $trial) {
                throw ValidationException::withMessages(['member' => 'Hội viên chưa có gói đã thanh toán đang hiệu lực.']);
            }

            $visit = match (true) {
                $membership !== null => $this->checkIns->create($customer, $membership),
                $sourceMembership !== null => CheckIn::query()->create([
                    'user_id' => $customer->id,
                    'membership_id' => null,
                    'trial_entitlement_id' => null,
                    'sql_membership_id' => $sourceMembership->MADKGT,
                    'checked_in_at' => now(),
                ]),
                default => CheckIn::query()->create([
                    'user_id' => $customer->id,
                    'membership_id' => null,
                    'trial_entitlement_id' => $trial->id,
                    'checked_in_at' => now(),
                ]),
            };
            $this->identities->syncCheckIn($visit);

            return $visit;
        });
    }

    public function checkOut(CheckIn $visit, User $staff): void
    {
        $this->sql->transaction(function () use ($visit, $staff): void {
            $lockedVisit = $this->checkIns->lockForUpdate($visit->id);

            if ($lockedVisit->checked_out_at !== null) {
                throw ValidationException::withMessages(['visit' => 'Lượt tập này đã được đóng.']);
            }

            $lockedVisit->update([
                'checked_out_at' => now(config('app.timezone')),
                'closed_by_user_id' => $staff->id,
            ]);
            $this->identities->syncCheckIn($lockedVisit);
        });
    }

    public function manualClose(CheckIn $visit, User $staff, string $reason): void
    {
        $this->sql->transaction(function () use ($visit, $staff, $reason): void {
            $lockedVisit = $this->checkIns->lockForUpdate($visit->id);

            if ($lockedVisit->checked_out_at !== null) {
                throw ValidationException::withMessages(['visit' => 'Lượt tập này đã được đóng.']);
            }

            $lockedVisit->update([
                'checked_out_at' => now(config('app.timezone')),
                'manual_close_reason' => $reason,
                'closed_by_user_id' => $staff->id,
            ]);
            $this->identities->syncCheckIn($lockedVisit);
        });
    }
}
