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
use Illuminate\Validation\ValidationException;

class MembershipOrderService
{
    public function __construct(
        private MembershipPeriod $period,
        private MembershipOrderRepository $orders,
        private MembershipRepository $memberships,
    ) {}

    public function create(User $customer, GymPackage $package, string $startsOn, string $paymentMethod): MembershipOrder
    {
        return DB::transaction(function () use ($customer, $package, $startsOn, $paymentMethod): MembershipOrder {
            $lockedCustomer = User::query()->lockForUpdate()->findOrFail($customer->id);
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
        return DB::transaction(function () use ($order, $staff, $amount, $paymentMethod, $reference): OrderPayment {
            $lockedOrder = $this->orders->lockForUpdate($order->id);

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

    public function confirm(MembershipOrder $order, User $staff, string $startsOn): Membership
    {
        return DB::transaction(function () use ($order, $staff, $startsOn): Membership {
            $lockedOrder = $this->orders->lockForUpdate($order->id);

            if ($lockedOrder->status === MembershipOrder::STATUS_PAID) {
                return $lockedOrder->membership()->firstOrFail();
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

            $package = $lockedOrder->gymPackage()->firstOrFail();
            $expiresOn = $this->period->expiresOn($startDate, $package->duration_months);
            $this->assertPeriodAvailable($customer, $startDate, $expiresOn);

            $lockedOrder->update([
                'start_date' => $startDate->toDateString(),
                'status' => MembershipOrder::STATUS_PAID,
                'paid_at' => now(config('app.timezone')),
                'confirmed_by_user_id' => $staff->id,
            ]);

            return $this->memberships->create([
                'membership_order_id' => $lockedOrder->id,
                'user_id' => $customer->id,
                'gym_package_id' => $package->id,
                'starts_on' => $startDate->toDateString(),
                'expires_on' => $expiresOn->toDateString(),
            ]);
        });
    }

    public function cancel(MembershipOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = $this->orders->lockForUpdate($order->id);

            if ($lockedOrder->status === MembershipOrder::STATUS_CANCELLED) {
                return;
            }

            if ($lockedOrder->status !== MembershipOrder::STATUS_PENDING || $lockedOrder->receivedAmount() > 0) {
                throw ValidationException::withMessages(['order' => 'Không thể hủy đơn đã nhận tiền hoặc đã thanh toán.']);
            }

            $lockedOrder->update(['status' => MembershipOrder::STATUS_CANCELLED]);
        });
    }

    public function requestCancellation(MembershipOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = $this->orders->lockForUpdate($order->id);

            if ($lockedOrder->status !== MembershipOrder::STATUS_PENDING) {
                throw ValidationException::withMessages(['order' => 'Chỉ có thể yêu cầu hủy đơn đang chờ thanh toán.']);
            }

            if ($lockedOrder->cancellation_requested_at === null) {
                $lockedOrder->update(['cancellation_requested_at' => now(config('app.timezone'))]);
            }
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
}
