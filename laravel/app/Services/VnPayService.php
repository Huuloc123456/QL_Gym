<?php

namespace App\Services;

use App\Models\MembershipOrder;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VnPayService
{
    public function __construct(
        private GymCommerceService $commerce,
        private MembershipOrderService $orders,
        private OriginalGymSql $sql,
        private GymRefundService $refunds,
    ) {}

    public function startMembershipOrder(MembershipOrder $order, User $user): string
    {
        abort_unless((int) $order->user_id === (int) $user->id, 404);
        if ($order->status !== MembershipOrder::STATUS_PENDING) {
            $this->fail('payment', 'Đơn này không còn chờ thanh toán.');
        }
        if ($order->cancellation_requested_at !== null) {
            $this->fail('payment', 'Đơn đang chờ xử lý yêu cầu hủy. Vui lòng liên hệ quầy trước khi thanh toán.');
        }
        $this->assertNoPaymentReview('membership_order', $order->id);

        $remaining = (int) $order->amount - $order->receivedAmount();
        if ($remaining < 1) {
            $this->fail('payment', 'Đơn không còn khoản tiền cần thanh toán online.');
        }

        return $this->start('membership_order', $order->id, $user, $remaining, 'Thanh toan don goi tap '.$order->id);
    }

    public function startInvoice(int $invoiceId, User $user): string
    {
        $invoice = $this->commerce->invoice($invoiceId, $user);
        if ($invoice->TRANGTHAI !== 'Chưa thanh toán') {
            $this->fail('payment', 'Hóa đơn này không còn chờ thanh toán.');
        }
        $this->assertNoPaymentReview('invoice', $invoiceId);
        $this->commerce->assertInvoiceHasDetails($invoiceId);
        $this->commerce->assertSupportedInvoiceKinds($invoiceId);
        $fulfillment = DB::table('gym_fulfillments')->where('invoice_id', $invoiceId)->first();
        if ($fulfillment?->expires_at
            && now(config('app.timezone'))->greaterThanOrEqualTo($fulfillment->expires_at)
            && $this->commerce->received($invoiceId) === 0) {
            $this->fail('payment', 'Đơn đã hết hạn thanh toán. Vui lòng đặt đơn mới.');
        }
        if ($fulfillment?->cancellation_requested) {
            $this->fail('payment', 'Đơn đang chờ xử lý yêu cầu hủy. Vui lòng liên hệ quầy trước khi thanh toán.');
        }
        $remaining = (int) $invoice->THANHTIEN - $this->commerce->received($invoiceId);
        if ($remaining < 1) {
            $this->fail('payment', 'Hóa đơn không còn khoản tiền cần thanh toán online.');
        }

        $deadline = $fulfillment?->expires_at ? Carbon::parse($fulfillment->expires_at, config('app.timezone')) : null;

        return $this->start('invoice', $invoiceId, $user, $remaining, 'Thanh toan hoa don '.$invoiceId, $deadline);
    }

    /** @param array<string, mixed> $parameters
     * @return array{RspCode: string, Message: string}
     */
    public function handleIpn(array $parameters): array
    {
        if ((string) config('vnpay.tmn_code') === '' || (string) config('vnpay.hash_secret') === '') {
            return $this->ipnResponse('97', 'Payment gateway is not configured');
        }

        $receivedHash = strtolower((string) ($parameters['vnp_SecureHash'] ?? ''));
        if ($receivedHash === '' || ! hash_equals($this->signature($parameters), $receivedHash)) {
            return $this->ipnResponse('97', 'Invalid signature');
        }

        if (! hash_equals((string) config('vnpay.tmn_code'), (string) ($parameters['vnp_TmnCode'] ?? ''))) {
            return $this->ipnResponse('97', 'Invalid terminal');
        }

        $reference = (string) ($parameters['vnp_TxnRef'] ?? '');
        $payment = DB::table('gym_online_payments')->where('txn_ref', $reference)->first();
        if (! $payment) {
            return $this->ipnResponse('01', 'Order not found');
        }

        if ((int) ($parameters['vnp_Amount'] ?? 0) !== (int) $payment->amount * 100) {
            return $this->ipnResponse('04', 'Invalid amount');
        }

        return $this->sql->transaction(function () use ($payment, $parameters): array {
            $lockedPayment = DB::table('gym_online_payments')->where('id', $payment->id)->lockForUpdate()->first();
            if ($lockedPayment->status === 'succeeded') {
                return $this->ipnResponse('00', 'Confirm Success');
            }
            if ($lockedPayment->status === 'review') {
                return $this->ipnResponse('00', 'Confirm Success');
            }
            if ($lockedPayment->status === 'failed') {
                return $this->ipnResponse('00', 'Confirm Success');
            }
            if ($lockedPayment->status === 'refunded') {
                return $this->ipnResponse('00', 'Confirm Success');
            }

            $successful = (string) ($parameters['vnp_ResponseCode'] ?? '') === '00'
                && (string) ($parameters['vnp_TransactionStatus'] ?? '') === '00';
            $update = [
                'response_code' => (string) ($parameters['vnp_ResponseCode'] ?? ''),
                'bank_code' => isset($parameters['vnp_BankCode']) ? (string) $parameters['vnp_BankCode'] : null,
                'provider_transaction_no' => isset($parameters['vnp_TransactionNo']) ? (string) $parameters['vnp_TransactionNo'] : null,
                'callback_payload' => json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => now(config('app.timezone')),
            ];

            if (! $successful) {
                DB::table('gym_online_payments')->where('id', $lockedPayment->id)->update($update + ['status' => 'failed']);

                return $this->ipnResponse('00', 'Confirm Success');
            }

            $reason = $this->manualReviewReason($lockedPayment);
            if ($reason !== null) {
                DB::table('gym_online_payments')->where('id', $lockedPayment->id)->update($update + ['status' => 'review', 'review_reason' => $reason, 'paid_at' => now(config('app.timezone'))]);
                $this->sql->audit('VNPay cần đối soát', $lockedPayment->target_type.':'.$lockedPayment->target_id, ['txn_ref' => $lockedPayment->txn_ref, 'reason' => $reason]);

                return $this->ipnResponse('00', 'Confirm Success');
            }

            DB::table('gym_online_payments')->where('id', $lockedPayment->id)->update($update + [
                'status' => 'succeeded',
                'paid_at' => now(config('app.timezone')),
            ]);

            try {
                if ($lockedPayment->target_type === 'invoice') {
                    $this->commerce->confirmOnline((int) $lockedPayment->target_id);
                } else {
                    $order = MembershipOrder::query()->findOrFail((int) $lockedPayment->target_id);
                    $this->orders->confirmOnline($order, $order->start_date->toDateString());
                }
            } catch (ValidationException $exception) {
                $reason = 'Không thể tự hoàn tất nghiệp vụ sau khi cổng báo đã thanh toán.';
                DB::table('gym_online_payments')->where('id', $lockedPayment->id)->update(['status' => 'review', 'review_reason' => $reason, 'updated_at' => now(config('app.timezone'))]);
                $this->sql->audit('VNPay cần đối soát', $lockedPayment->target_type.':'.$lockedPayment->target_id, ['txn_ref' => $lockedPayment->txn_ref, 'reason' => $reason]);
            }

            return $this->ipnResponse('00', 'Confirm Success');
        });
    }

    public function paymentForReturn(string $reference): object
    {
        $payment = DB::table('gym_online_payments')->where('txn_ref', $reference)
            ->select(['txn_ref', 'target_type', 'target_id', 'amount', 'status', 'paid_at'])
            ->first();
        abort_unless($payment, 404);

        return $payment;
    }

    public function resolveReview(int $paymentId, User $manager, string $resolution, array $data): void
    {
        abort_unless($manager->role === 'manager', 403);
        if (! in_array($resolution, ['confirmed', 'refunded'], true)) {
            $this->fail('resolution', 'Cách xử lý không hợp lệ.');
        }

        $this->sql->transaction(function () use ($paymentId, $manager, $resolution, $data): void {
            $payment = DB::table('gym_online_payments')->where('id', $paymentId)->lockForUpdate()->first();
            abort_unless($payment, 404);
            if ($payment->status !== 'review' || $payment->review_resolution !== null) {
                $this->fail('payment', 'Giao dịch này đã được xử lý hoặc không còn cần đối soát.');
            }

            $timestamp = now(config('app.timezone'));
            if ($resolution === 'confirmed') {
                DB::table('gym_online_payments')->where('id', $paymentId)->update([
                    'status' => 'succeeded',
                    'paid_at' => $payment->paid_at ?? $timestamp,
                    'review_resolution' => 'confirmed',
                    'reviewed_by_user_id' => $manager->id,
                    'reviewed_at' => $timestamp,
                    'resolution_reference' => $data['reference'] ?? null,
                    'resolution_note' => $data['note'],
                    'updated_at' => $timestamp,
                ]);

                if ($payment->target_type === 'invoice') {
                    $this->commerce->confirmOnline((int) $payment->target_id, allowExpired: true, startDate: $data['start_date'] ?? null);
                } elseif ($payment->target_type === 'membership_order') {
                    $order = MembershipOrder::query()->findOrFail((int) $payment->target_id);
                    $order->update(['cancellation_requested_at' => null]);
                    $order->refresh();
                    $this->orders->confirmOnline($order, $data['start_date'] ?? $order->start_date->toDateString());
                } else {
                    $this->fail('payment', 'Loại đơn của giao dịch không được hỗ trợ.');
                }
            } else {
                $this->refunds->recordOnlineReviewRefund($manager, $paymentId, $data['reference'], $data['note']);
                DB::table('gym_online_payments')->where('id', $paymentId)->update([
                    'status' => 'refunded',
                    'review_resolution' => 'refunded',
                    'reviewed_by_user_id' => $manager->id,
                    'reviewed_at' => $timestamp,
                    'resolution_reference' => $data['reference'],
                    'resolution_note' => $data['note'],
                    'updated_at' => $timestamp,
                ]);

                if ($payment->target_type === 'invoice'
                    && DB::table('gym_fulfillments')->where('invoice_id', $payment->target_id)->value('cancellation_requested')
                    && $this->commerce->received((int) $payment->target_id) === 0) {
                    $this->commerce->cancel((int) $payment->target_id, $manager);
                } elseif ($payment->target_type === 'membership_order') {
                    $order = MembershipOrder::query()->findOrFail((int) $payment->target_id);
                    if ($order->cancellation_requested_at !== null && $order->receivedAmount() === 0) {
                        $this->orders->cancel($order);
                    }
                }
            }

            $this->sql->audit(
                $resolution === 'confirmed' ? 'Xác nhận giao dịch VNPay đối soát' : 'Ghi nhận hoàn tiền VNPay',
                $payment->target_type.':'.$payment->target_id,
                ['txn_ref' => $payment->txn_ref, 'resolution' => $resolution, 'reference' => $data['reference'] ?? null, 'note' => $data['note']],
            );
        });
    }

    public function reviewQueue(): Collection
    {
        return DB::table('gym_online_payments as p')
            ->join('users as u', 'u.id', '=', 'p.user_id')
            ->leftJoin('HOADON as h', function ($join): void {
                $join->on('h.MAHD', '=', 'p.target_id')->where('p.target_type', '=', 'invoice');
            })
            ->leftJoin('membership_orders as o', function ($join): void {
                $join->on('o.id', '=', 'p.target_id')->where('p.target_type', '=', 'membership_order');
            })
            ->leftJoin('gym_packages as g', 'g.id', '=', 'o.gym_package_id')
            ->where('p.status', 'review')
            ->select('p.*', 'u.name as customer_name', 'h.TRANGTHAI as invoice_status', 'o.status as legacy_status')
            ->selectRaw("CASE WHEN p.target_type = 'invoice' THEN CONCAT('Hóa đơn #', p.target_id) ELSE CONCAT('Đơn gói #', p.target_id, ' · ', COALESCE(g.name, 'Gói tập')) END as order_label")
            ->orderBy('p.created_at')
            ->orderBy('p.id')
            ->get();
    }

    /** @param array<string, mixed> $parameters */
    public function signature(array $parameters): string
    {
        unset($parameters['vnp_SecureHash'], $parameters['vnp_SecureHashType']);
        $parameters = array_filter($parameters, static fn (mixed $value, string|int $key): bool => str_starts_with((string) $key, 'vnp_') && is_scalar($value) && (string) $value !== '', ARRAY_FILTER_USE_BOTH);
        ksort($parameters);
        $data = http_build_query($parameters, '', '&', PHP_QUERY_RFC1738);

        return hash_hmac('sha512', $data, (string) config('vnpay.hash_secret'));
    }

    private function start(string $targetType, int $targetId, User $user, int $amount, string $orderInfo, ?CarbonInterface $maximumExpiry = null): string
    {
        $terminal = (string) config('vnpay.tmn_code');
        $secret = (string) config('vnpay.hash_secret');
        if ($terminal === '' || $secret === '') {
            $this->fail('payment', 'Thanh toán online chưa được cấu hình khóa VNPay trên máy chủ. Vui lòng thanh toán tại quầy.');
        }

        $payment = $this->sql->transaction(function () use ($targetType, $targetId, $user, $amount, $maximumExpiry): object {
            $existing = DB::table('gym_online_payments')->where('target_type', $targetType)
                ->where('target_id', $targetId)->where('user_id', $user->id)->where('status', 'pending')
                ->where('expires_at', '>', now(config('app.timezone')))->orderByDesc('id')->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $createdAt = now(config('app.timezone'));
            $expiresAt = $createdAt->copy()->addMinutes(max(5, min(30, (int) config('vnpay.payment_minutes', 15))));
            if ($maximumExpiry !== null && $maximumExpiry->lessThan($expiresAt)) {
                $expiresAt = $maximumExpiry;
            }
            $reference = Str::upper(Str::random(12)).Str::upper(Str::random(12));
            $id = DB::table('gym_online_payments')->insertGetId([
                'target_type' => $targetType,
                'target_id' => $targetId,
                'user_id' => $user->id,
                'txn_ref' => $reference,
                'amount' => $amount,
                'status' => 'pending',
                'expires_at' => $expiresAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            return DB::table('gym_online_payments')->where('id', $id)->first();
        });

        $createdAt = now(config('app.timezone'));
        $parameters = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $terminal,
            'vnp_Amount' => (int) $payment->amount * 100,
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => $payment->txn_ref,
            'vnp_OrderInfo' => $orderInfo,
            'vnp_OrderType' => 'other',
            'vnp_Locale' => 'vn',
            'vnp_ReturnUrl' => config('vnpay.return_url') ?: route('vnpay.return'),
            'vnp_IpAddr' => request()->ip() ?: '127.0.0.1',
            'vnp_CreateDate' => $createdAt->format('YmdHis'),
            'vnp_ExpireDate' => Carbon::parse($payment->expires_at, config('app.timezone'))->format('YmdHis'),
        ];
        ksort($parameters);
        $parameters['vnp_SecureHash'] = $this->signature($parameters);

        return (string) config('vnpay.url').'?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC1738);
    }

    private function manualReviewReason(object $payment): ?string
    {
        if (now(config('app.timezone'))->greaterThan($payment->expires_at)) {
            return 'Giao dịch đến sau thời hạn thanh toán.';
        }

        if ($payment->target_type === 'invoice') {
            $invoice = DB::table('HOADON')->where('MAHD', $payment->target_id)->first();
            if (! $invoice || (int) $invoice->MAKH !== (int) User::query()->whereKey($payment->user_id)->value('sql_customer_id')) {
                return 'Hóa đơn không khớp với tài khoản đã tạo giao dịch.';
            }
            if ($invoice->TRANGTHAI !== 'Chưa thanh toán') {
                return 'Hóa đơn đã được đóng hoặc thanh toán bằng phương thức khác.';
            }
            if (DB::table('gym_fulfillments')->where('invoice_id', $invoice->MAHD)->value('cancellation_requested')) {
                return 'Khách đã gửi yêu cầu hủy hóa đơn.';
            }
            if ($this->commerce->received((int) $invoice->MAHD) + (int) $payment->amount > (int) $invoice->THANHTIEN) {
                return 'Số tiền từ các phương thức thanh toán đã vượt tổng hóa đơn.';
            }

            return null;
        }

        $order = MembershipOrder::query()->find((int) $payment->target_id);
        if (! $order || (int) $order->user_id !== (int) $payment->user_id) {
            return 'Đơn tập không khớp với tài khoản đã tạo giao dịch.';
        }
        if ($order->status !== MembershipOrder::STATUS_PENDING) {
            return 'Đơn đã bị hủy hoặc thanh toán bằng phương thức khác.';
        }
        if ($order->cancellation_requested_at !== null) {
            return 'Khách đã gửi yêu cầu hủy đơn.';
        }
        if ($order->receivedAmount() + (int) $payment->amount > (int) $order->amount) {
            return 'Số tiền từ các phương thức thanh toán đã vượt tổng đơn.';
        }

        return null;
    }

    private function assertNoPaymentReview(string $targetType, int $targetId): void
    {
        if (DB::table('gym_online_payments')->where('target_type', $targetType)->where('target_id', $targetId)->where('status', 'review')->exists()) {
            $this->fail('payment', 'Đơn đang có giao dịch VNPay chờ đối soát. Vui lòng chờ quản lý xử lý trước khi thanh toán lại.');
        }
    }

    /** @return array{RspCode: string, Message: string} */
    private function ipnResponse(string $code, string $message): array
    {
        return ['RspCode' => $code, 'Message' => $message];
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
