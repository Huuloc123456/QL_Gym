<?php

namespace App\Services;

use App\Models\GymSetting;
use App\Models\TrialEntitlement;
use App\Models\TrialRequest;
use App\Models\User;
use App\Repositories\MembershipRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrialRegistrationService
{
    public function __construct(private OriginalGymSql $sql, private GymIdentityService $identities, private MembershipRepository $memberships, private GymPortalService $portal) {}

    public function assertEligible(User $customer): void
    {
        $phone = self::normalizePhone((string) $customer->phone);
        if (! $customer->is_active || $phone === '') {
            throw ValidationException::withMessages(['trial' => 'Hãy cập nhật số điện thoại và kiểm tra trạng thái tài khoản trước khi đăng ký.']);
        }
        if (TrialRequest::query()->where('user_id', $customer->id)->where('status', 'pending')->exists()
            || TrialEntitlement::query()->where('user_id', $customer->id)->orWhere('verified_phone', $phone)->exists()) {
            throw ValidationException::withMessages(['trial' => 'Bạn đã có yêu cầu hoặc quyền tập thử.']);
        }
        $oldPhones = TrialRequest::query()->whereNotNull('sql_membership_id')->pluck('phone');
        if ($oldPhones->contains(fn (string $oldPhone): bool => self::normalizePhone($oldPhone) === $phone)) {
            throw ValidationException::withMessages(['trial' => 'Số điện thoại này đã có lịch sử tập thử; cần đối chiếu.']);
        }
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $customerId = $this->identities->customer($customer);
        if ($this->memberships->latestFutureExpiry($customer, $today) !== null
            || DB::table('DANGKYGOITAP')->where('MAKH', $customerId)->where('TRANGTHAI', 'Còn hiệu lực')->where('NGAYKETTHUC', '>=', now())->exists()) {
            throw ValidationException::withMessages(['trial' => 'Khách đã có gói tập đang hoặc sắp có hiệu lực.']);
        }
    }

    public function approve(TrialRequest $request, User $staff, string $startDate): void
    {
        abort_unless($staff->canManageTrialRequests(), 403);
        $this->sql->transaction(function () use ($request, $staff, $startDate): void {
            $trial = TrialRequest::query()->lockForUpdate()->findOrFail($request->id);
            if (TrialEntitlement::query()->where('trial_request_id', $trial->id)->exists()) {
                return;
            }
            if ($trial->status !== 'pending' || ! $trial->user_id) {
                throw ValidationException::withMessages(['trial' => 'Yêu cầu cũ cần đối chiếu tài khoản trước khi duyệt.']);
            }
            $customer = User::query()->lockForUpdate()->findOrFail($trial->user_id);
            $phone = self::normalizePhone((string) $customer->phone);
            if (! $customer->is_active || $customer->role !== 'customer' || $phone === '' || $phone !== self::normalizePhone($trial->phone)) {
                throw ValidationException::withMessages(['trial' => 'Tài khoản hoặc số điện thoại đã thay đổi; hãy đối chiếu lại với khách.']);
            }
            if (TrialEntitlement::query()->where('user_id', $customer->id)->orWhere('verified_phone', $phone)->exists()) {
                throw ValidationException::withMessages(['trial' => 'Khách hoặc số điện thoại này đã được cấp quyền tập thử.']);
            }
            $oldPhones = TrialRequest::query()->whereNotNull('sql_membership_id')->pluck('phone');
            if ($oldPhones->contains(fn (string $oldPhone): bool => self::normalizePhone($oldPhone) === $phone)) {
                throw ValidationException::withMessages(['trial' => 'Số điện thoại này đã có lịch sử tập thử; cần đối chiếu.']);
            }
            $customerId = $this->identities->customer($customer);
            if ($this->memberships->latestFutureExpiry($customer, CarbonImmutable::now(config('app.timezone'))->startOfDay()) !== null
                || DB::table('DANGKYGOITAP')->where('MAKH', $customerId)->where('TRANGTHAI', 'Còn hiệu lực')->where('NGAYKETTHUC', '>=', now())->exists()) {
                throw ValidationException::withMessages(['trial' => 'Khách đã có gói tập đang hoặc sắp có hiệu lực.']);
            }
            $start = CarbonImmutable::parse($startDate, config('app.timezone'))->startOfDay();
            if ($start->lt(CarbonImmutable::now(config('app.timezone'))->startOfDay())) {
                throw ValidationException::withMessages(['start_date' => 'Ngày bắt đầu không được ở quá khứ.']);
            }
            $days = (int) GymSetting::current()->trial_days;
            if ($days < 1 || $days > 7) {
                throw ValidationException::withMessages(['trial' => 'Thời lượng tập thử cần được quản lý cấu hình từ 1 đến 7 ngày.']);
            }
            TrialEntitlement::query()->create(['trial_request_id' => $trial->id, 'user_id' => $customer->id, 'verified_phone' => $phone,
                'starts_at' => $start, 'ends_at' => $start->addDays($days), 'approved_by' => $staff->id]);
            $trial->update(['status' => 'approved', 'decided_by' => $staff->id, 'decided_at' => now()]);
            $this->portal->message($customer, 'trial:'.$trial->id.':approved', 'Đăng ký tập thử đã được duyệt',
                'Quyền tập thử của bạn bắt đầu từ '.$start->format('d/m/Y').'. Thời hạn theo cấu hình phòng tập.', route('trials.create', absolute: false));
            $this->sql->audit('Duyệt tập thử', 'trial_request:'.$trial->id);
        });
    }

    public function linkLegacyRequest(TrialRequest $request, User $manager, string $username, string $confirmedPhone): void
    {
        abort_unless(User::query()->whereKey($manager->id)->where('role', 'manager')->where('is_active', true)->exists(), 403);

        $this->sql->transaction(function () use ($request, $manager, $username, $confirmedPhone): void {
            $trial = TrialRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($trial->user_id !== null || $trial->sql_membership_id !== null || ! in_array($trial->status, ['received', 'contacted'], true)) {
                throw ValidationException::withMessages(['trial' => 'Yêu cầu này không còn thuộc nhóm dữ liệu cũ cần đối chiếu.']);
            }

            $customer = User::query()->where('username', $username)->lockForUpdate()->first();
            if (! $customer || $customer->role !== 'customer' || ! $customer->is_active) {
                throw ValidationException::withMessages(['trial' => 'Không tìm thấy tài khoản hội viên đang hoạt động.']);
            }

            $snapshotPhone = self::normalizePhone((string) $trial->phone);
            $accountPhone = self::normalizePhone((string) $customer->phone);
            if ($snapshotPhone === '' || $snapshotPhone !== $accountPhone || $snapshotPhone !== self::normalizePhone($confirmedPhone)) {
                throw ValidationException::withMessages(['trial' => 'Số điện thoại tài khoản không khớp bản ghi cũ; giữ nguyên yêu cầu để tiếp tục đối chiếu.']);
            }

            if (TrialRequest::query()->where('user_id', $customer->id)->where('status', 'pending')->exists()
                || TrialEntitlement::query()->where('user_id', $customer->id)->orWhere('verified_phone', $accountPhone)->exists()) {
                throw ValidationException::withMessages(['trial' => 'Tài khoản này đã có yêu cầu hoặc quyền tập thử.']);
            }

            $legacyPhones = TrialRequest::query()->whereNotNull('sql_membership_id')->pluck('phone');
            if ($legacyPhones->contains(fn (string $phone): bool => self::normalizePhone($phone) === $accountPhone)) {
                throw ValidationException::withMessages(['trial' => 'Tài khoản này có lịch sử tập thử cũ đã duyệt; cần đối chiếu trước khi cấp thêm.']);
            }

            $trial->update([
                'user_id' => $customer->id,
                'status' => 'pending',
                'desired_date' => now(config('app.timezone'))->toDateString(),
                'handled_by' => $manager->id,
                'handled_at' => now(config('app.timezone')),
            ]);
            $this->sql->audit('Đối chiếu và gắn yêu cầu tập thử cũ', 'trial_request:'.$trial->id, ['customer_id' => $customer->id]);
        });
    }

    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return str_starts_with($digits, '84') ? '0'.substr($digits, 2) : $digits;
    }

    public function cancel(TrialRequest $request, User $customer): void
    {
        $this->sql->transaction(function () use ($request, $customer): void {
            $trial = TrialRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless((int) $trial->user_id === $customer->id, 404);
            if ($trial->status !== 'pending') {
                throw ValidationException::withMessages(['trial' => 'Chỉ có thể hủy yêu cầu đang chờ duyệt.']);
            }
            $trial->update(['status' => 'cancelled', 'decided_by' => $customer->id, 'decided_at' => now()]);
            $this->sql->audit('Hủy yêu cầu tập thử', 'trial_request:'.$trial->id);
        });
    }

    public function reject(TrialRequest $request, User $staff, string $reason): void
    {
        abort_unless($staff->canManageTrialRequests(), 403);
        $this->sql->transaction(function () use ($request, $staff, $reason): void {
            $trial = TrialRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($trial->status !== 'pending') {
                throw ValidationException::withMessages(['trial' => 'Yêu cầu không còn chờ duyệt.']);
            }
            $trial->update(['status' => 'rejected', 'decision_reason' => $reason, 'decided_by' => $staff->id, 'decided_at' => now()]);
            $customer = $trial->user_id ? User::query()->find($trial->user_id) : null;
            if ($customer) {
                $this->portal->message($customer, 'trial:'.$trial->id.':rejected', 'Đăng ký tập thử chưa được duyệt',
                    'Yêu cầu tập thử chưa được duyệt. Lý do: '.$reason, route('trials.create', absolute: false));
            }
            $this->sql->audit('Từ chối tập thử', 'trial_request:'.$trial->id);
        });
    }

    public function reschedule(TrialRequest $request, User $staff, string $startDate): void
    {
        abort_unless($staff->canManageTrialRequests(), 403);
        $this->sql->transaction(function () use ($request, $staff, $startDate): void {
            $trial = TrialRequest::query()->lockForUpdate()->findOrFail($request->id);
            $entitlement = TrialEntitlement::query()->where('trial_request_id', $trial->id)->lockForUpdate()->first();
            if ($trial->status !== 'approved' || ! $entitlement || $entitlement->revoked_at !== null) {
                throw ValidationException::withMessages(['trial' => 'Chỉ đổi lịch được cho quyền tập thử đang hiệu lực.']);
            }
            if ($entitlement->checkIns()->exists()) {
                throw ValidationException::withMessages(['trial' => 'Không thể đổi lịch sau khi khách đã vào tập; hãy giữ nguyên lịch sử.']);
            }

            $start = CarbonImmutable::parse($startDate, config('app.timezone'))->startOfDay();
            if ($start->lt(CarbonImmutable::now(config('app.timezone'))->startOfDay())) {
                throw ValidationException::withMessages(['start_date' => 'Ngày bắt đầu không được ở quá khứ.']);
            }
            $days = (int) GymSetting::current()->trial_days;
            if ($days < 1 || $days > 7) {
                throw ValidationException::withMessages(['trial' => 'Thời lượng tập thử cần được quản lý cấu hình từ 1 đến 7 ngày.']);
            }

            $entitlement->update(['starts_at' => $start, 'ends_at' => $start->addDays($days)]);
            $trial->update(['desired_date' => $start->toDateString()]);
            $this->notifyTrialCustomer($trial, 'rescheduled', 'Lịch tập thử đã được đổi',
                'Lịch tập thử mới bắt đầu ngày '.$start->format('d/m/Y').'.');
            $this->sql->audit('Đổi lịch tập thử', 'trial_request:'.$trial->id, ['start_date' => $start->toDateString(), 'handled_by' => $staff->id]);
        });
    }

    public function revoke(TrialRequest $request, User $staff, string $reason): void
    {
        abort_unless($staff->canManageTrialRequests(), 403);
        $this->sql->transaction(function () use ($request, $staff, $reason): void {
            $trial = TrialRequest::query()->lockForUpdate()->findOrFail($request->id);
            $entitlement = TrialEntitlement::query()->where('trial_request_id', $trial->id)->lockForUpdate()->first();
            if ($trial->status !== 'approved' || ! $entitlement || $entitlement->revoked_at !== null) {
                throw ValidationException::withMessages(['trial' => 'Không tìm thấy quyền tập thử đang có hiệu lực để thu hồi.']);
            }

            $entitlement->update(['revoked_at' => now(), 'revocation_reason' => $reason, 'revoked_by' => $staff->id]);
            $this->notifyTrialCustomer($trial, 'revoked', 'Quyền tập thử đã bị thu hồi',
                'Quyền tập thử đã dừng. Lý do: '.$reason);
            $this->sql->audit('Thu hồi quyền tập thử', 'trial_request:'.$trial->id, ['reason' => $reason, 'revoked_by' => $staff->id]);
        });
    }

    private function notifyTrialCustomer(TrialRequest $trial, string $event, string $title, string $body): void
    {
        $customer = $trial->user_id ? User::query()->find($trial->user_id) : null;
        if ($customer) {
            $this->portal->message($customer, 'trial:'.$trial->id.':'.$event, $title, $body, route('trials.create', absolute: false));
        }
    }
}
