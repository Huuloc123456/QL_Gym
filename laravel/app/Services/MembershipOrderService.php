<?php

namespace App\Services;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOrder;
use App\Models\OrderPayment;
use App\Models\User;
use App\Repositories\MembershipOrderRepository;
use App\Repositories\MembershipRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MembershipOrderService
{
    public function __construct(
        private MembershipPeriod $period,
        private MembershipOrderRepository $orders,
        private MembershipRepository $memberships,
        private GymIdentityService $identities,
        private OriginalGymSql $sql,
    ) {}

    public function create(User $customer, GymPackage $package, string $startsOn, string $paymentMethod): MembershipOrder
    {
        return $this->sql->transaction(function () use ($customer, $package, $startsOn, $paymentMethod): MembershipOrder {
            $lockedCustomer = User::query()->lockForUpdate()->findOrFail($customer->id);
            $package = GymPackage::query()->lockForUpdate()->findOrFail($package->id);
            $startDate = CarbonImmutable::parse($startsOn, config('app.timezone'))->startOfDay();
            $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

            if (! $package->is_active) {
                throw ValidationException::withMessages(['gym_package_id' => 'Gói tập này hiện không còn mở bán.']);
            }

            if ($startDate->lt($today)) {
                throw ValidationException::withMessages(['start_date' => 'Ngày bắt đầu không được trước ngày đặt mua.']);
            }

            $expiresOn = $this->period->expiresOn($startDate, $package->duration_months);
            $this->assertPeriodAvailable($lockedCustomer, $startDate, $expiresOn);

            return $this->orders->createPending([
                'user_id' => $lockedCustomer->id,
                'gym_package_id' => $package->id,
                'amount' => $package->price,
                'duration_months' => $package->duration_months,
                'start_date' => $startDate->toDateString(),
                'payment_method' => $paymentMethod,
                'status' => MembershipOrder::STATUS_PENDING,
            ]);
        });
    }

    public function recordPayment(
        MembershipOrder $order,
        User $staff,
        int $amount,
        string $paymentMethod,
        ?string $reference = null,
    ): OrderPayment {
        return $this->sql->transaction(function () use ($order, $staff, $amount, $paymentMethod, $reference): OrderPayment {
            $lockedOrder = $this->orders->lockForUpdate($order->id);

            $this->assertNoPaymentReview($lockedOrder);

            if ($lockedOrder->cancellation_requested_at !== null) {
                throw ValidationException::withMessages(['order' => 'Khách đã yêu cầu hủy; nhân viên cần hủy đơn hoặc xác nhận tiếp tục trước khi thu tiền.']);
            }

            if ($lockedOrder->status !== MembershipOrder::STATUS_PENDING) {
                throw ValidationException::withMessages(['order' => 'Chỉ ghi nhận tiền cho đơn đang chờ thanh toán.']);
            }

            $received = $lockedOrder->receivedAmount();

            if ($amount + $received > $lockedOrder->amount) {
                throw ValidationException::withMessages(['amount' => 'Số tiền ghi nhận vượt quá số tiền còn phải thu.']);
            }

            return $lockedOrder->payments()->create([
                'received_by' => $staff->id,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'reference' => $reference,
                'received_at' => now(config('app.timezone')),
            ]);
        });
    }

    public function confirm(MembershipOrder $order, User $staff, string $startsOn, ?int $legacyDurationMonths = null): Membership
    {
        return $this->confirmWithActor($order, $staff, $startsOn, $legacyDurationMonths)['membership'];
    }

    /**
     * @return array{membership: Membership, already_confirmed: bool}
     */
    public function confirmForStaff(MembershipOrder $order, User $staff, string $startsOn, ?int $legacyDurationMonths = null): array
    {
        return $this->confirmWithActor($order, $staff, $startsOn, $legacyDurationMonths);
    }

    public function confirmOnline(MembershipOrder $order, string $startsOn): Membership
    {
        return $this->confirmWithActor($order, null, $startsOn)['membership'];
    }

    /**
     * @return array{membership: Membership, already_confirmed: bool}
     */
    private function confirmWithActor(MembershipOrder $order, ?User $staff, string $startsOn, ?int $legacyDurationMonths = null): array
    {
        return $this->sql->transaction(function () use ($order, $staff, $startsOn, $legacyDurationMonths): array {
            $lockedOrder = $this->orders->lockForUpdate($order->id);

            if ($lockedOrder->status === MembershipOrder::STATUS_PAID) {
                return [
                    'membership' => $lockedOrder->membership()->firstOrFail(),
                    'already_confirmed' => true,
                ];
            }

            $this->assertNoPaymentReview($lockedOrder);

            if ($lockedOrder->cancellation_requested_at !== null) {
                throw ValidationException::withMessages(['order' => 'Khách đã yêu cầu hủy; nhân viên cần hủy đơn hoặc xác nhận tiếp tục trước khi chốt.']);
            }

            if ($lockedOrder->status !== MembershipOrder::STATUS_PENDING) {
                throw ValidationException::withMessages(['order' => 'Đơn đã bị hủy, không thể xác nhận thanh toán.']);
            }

            $customer = User::query()->lockForUpdate()->findOrFail($lockedOrder->user_id);

            if ($lockedOrder->receivedAmount() < $lockedOrder->amount) {
                throw ValidationException::withMessages(['payment' => 'Chưa nhận đủ tiền để xác nhận đơn.']);
            }

            $startDate = CarbonImmutable::parse($startsOn, config('app.timezone'))->startOfDay();
            $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();

            if ($startDate->lt($today)) {
                throw ValidationException::withMessages(['start_date' => 'Ngày bắt đầu đã qua. Hãy chốt ngày mới với khách.']);
            }

            $durationMonths = $lockedOrder->duration_months ?? $legacyDurationMonths;

            if ($durationMonths === null || $durationMonths < 1 || $durationMonths > 65535) {
                throw ValidationException::withMessages(['duration_months' => 'Hãy xác minh và nhập thời hạn đã thỏa thuận của đơn cũ (1–65535 tháng).']);
            }

            $expiresOn = $this->period->expiresOn($startDate, $durationMonths);
            $this->assertPeriodAvailable($customer, $startDate, $expiresOn);

            $lockedOrder->update([
                'start_date' => $startDate->toDateString(),
                'duration_months' => $durationMonths,
                'status' => MembershipOrder::STATUS_PAID,
                'paid_at' => now(config('app.timezone')),
                'confirmed_by_user_id' => $staff?->id,
            ]);

            $membership = $this->memberships->create([
                'membership_order_id' => $lockedOrder->id,
                'user_id' => $customer->id,
                'gym_package_id' => $lockedOrder->gym_package_id,
                'starts_on' => $startDate->toDateString(),
                'expires_on' => $expiresOn->toDateString(),
            ]);
            if (Schema::hasTable('GOITAP')) {
                $this->identities->recordMembership($membership, $lockedOrder);
            }

            return [
                'membership' => $membership,
                'already_confirmed' => false,
            ];
        });
    }

    public function cancel(MembershipOrder $order): bool
    {
        return $this->sql->transaction(function () use ($order): bool {
            $lockedOrder = $this->orders->lockForUpdate($order->id);

            if ($lockedOrder->status === MembershipOrder::STATUS_CANCELLED) {
                return false;
            }

            $this->assertNoPaymentReview($lockedOrder);

            if ($lockedOrder->status !== MembershipOrder::STATUS_PENDING || $lockedOrder->receivedAmount() > 0) {
                throw ValidationException::withMessages(['order' => 'Không thể hủy đơn đã nhận tiền hoặc đã thanh toán.']);
            }

            $lockedOrder->update(['status' => MembershipOrder::STATUS_CANCELLED]);

            return true;
        });
    }

    public function requestCancellation(MembershipOrder $order): void
    {
        $this->sql->transaction(function () use ($order): void {
            $lockedOrder = $this->orders->lockForUpdate($order->id);

            if ($lockedOrder->status !== MembershipOrder::STATUS_PENDING) {
                throw ValidationException::withMessages(['order' => 'Chỉ có thể yêu cầu hủy đơn đang chờ thanh toán.']);
            }

            if ($lockedOrder->cancellation_requested_at === null) {
                $lockedOrder->update(['cancellation_requested_at' => now(config('app.timezone'))]);
            }
        });
    }

    public function resumeCancellationRequest(MembershipOrder $order, User $staff): bool
    {
        abort_unless($staff->canManageOrders(), 403);

        return $this->sql->transaction(function () use ($order): bool {
            $lockedOrder = $this->orders->lockForUpdate($order->id);
            $this->assertNoPaymentReview($lockedOrder);

            if ($lockedOrder->status !== MembershipOrder::STATUS_PENDING) {
                throw ValidationException::withMessages(['order' => 'Chỉ có thể tiếp tục đơn đang chờ thanh toán.']);
            }

            if ($lockedOrder->cancellation_requested_at === null) {
                return false;
            }

            $lockedOrder->update(['cancellation_requested_at' => null]);
            $this->sql->audit('Tiếp tục đơn sau yêu cầu hủy', 'MembershipOrder:'.$lockedOrder->id);

            return true;
        });
    }

    public function suggestedStartDate(User $customer): CarbonImmutable
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $latestExpiry = $this->memberships->latestFutureExpiry($customer, $today);

        if ($latestExpiry === null) {
            return $today;
        }

        $suggestedDate = CarbonImmutable::parse($latestExpiry, config('app.timezone'))->startOfDay();

        return $suggestedDate->max($today);
    }

    private function assertPeriodAvailable(User $customer, CarbonImmutable $startsOn, CarbonImmutable $expiresOn): void
    {
        $hasOverlap = $this->memberships->periodOverlaps($customer, $startsOn, $expiresOn);

        if ($hasOverlap) {
            throw ValidationException::withMessages(['start_date' => 'Khoảng tập dự kiến bị trùng với một gói đã thanh toán.']);
        }
    }

    private function assertNoPaymentReview(MembershipOrder $order): void
    {
        if (DB::table('gym_online_payments')->where('target_type', 'membership_order')->where('target_id', $order->id)->where('status', 'review')->exists()) {
            throw ValidationException::withMessages(['payment' => 'Đơn có giao dịch VNPay đang đối soát; quản lý cần xử lý trước khi thu, chốt hoặc hủy.']);
        }
    }
}
