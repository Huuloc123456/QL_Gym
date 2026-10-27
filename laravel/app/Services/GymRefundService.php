<?php

namespace App\Services;

use App\Models\GymRefund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GymRefundService
{
    public function __construct(private OriginalGymSql $sql) {}

    /** @param array{source_type: string, source_id: int, amount: int, reference: string, reason: string, token: string} $data */
    public function record(User $manager, array $data): bool
    {
        abort_unless($manager->role === 'manager', 403);

        return $this->sql->transaction(function () use ($manager, $data): bool {
            abort_unless(User::query()->whereKey($manager->id)->where('role', 'manager')->where('is_active', true)->exists(), 403);
            $repeat = GymRefund::query()->where('token', $data['token'])->first();
            if ($repeat) {
                abort_unless($repeat->source_type === $data['source_type'] && (int) $repeat->source_id === (int) $data['source_id'] && (int) $repeat->amount === (int) $data['amount'], 422);

                return false;
            }
            if ($data['source_type'] === 'receipt') {
                $source = DB::table('gym_receipts')->where('id', $data['source_id'])->lockForUpdate()->first();
                abort_unless($source, 404);
                $targetType = 'invoice';
                $targetId = (int) $source->invoice_id;
                $method = $source->method;
                $status = DB::table('HOADON')->where('MAHD', $targetId)->value('TRANGTHAI');
                if ($status !== 'Chưa thanh toán') {
                    throw ValidationException::withMessages(['source_id' => 'Chỉ ghi nhận hoàn qua luồng này cho hóa đơn chưa hoàn tất.']);
                }
            } elseif ($data['source_type'] === 'order_payment') {
                $source = DB::table('order_payments')->where('id', $data['source_id'])->lockForUpdate()->first();
                abort_unless($source, 404);
                $targetType = 'membership_order';
                $targetId = (int) $source->membership_order_id;
                $method = $source->payment_method;
                $status = DB::table('membership_orders')->where('id', $targetId)->whereNull('sql_invoice_id')->value('status');
                if ($status !== 'pending') {
                    throw ValidationException::withMessages(['source_id' => 'Chỉ ghi nhận hoàn qua luồng này cho đơn gói chưa hoàn tất.']);
                }
            } else {
                abort(422);
            }
            if (GymRefund::query()->where('source_type', $data['source_type'])
                ->where('source_id', $data['source_id'])->where('reference', $data['reference'])->exists()) {
                throw ValidationException::withMessages(['reference' => 'Mã chứng từ này đã được dùng cho khoản hoàn của khoản thu.']);
            }

            $alreadyRefunded = (int) GymRefund::query()->where('source_type', $data['source_type'])->where('source_id', $data['source_id'])->sum('amount');
            if ($data['amount'] < 1 || $data['amount'] > (int) $source->amount - $alreadyRefunded) {
                throw ValidationException::withMessages(['amount' => 'Số tiền hoàn vượt khoản thu chưa hoàn.']);
            }
            GymRefund::query()->create($data + ['target_type' => $targetType, 'target_id' => $targetId, 'method' => $method, 'recorded_by' => $manager->id, 'refunded_at' => now()]);
            $this->sql->audit('Ghi nhận hoàn tiền', $targetType.':'.$targetId, ['source_type' => $data['source_type'], 'source_id' => $data['source_id'], 'amount' => $data['amount']]);

            return true;
        });
    }

    public function recordOnlineReviewRefund(User $manager, int $paymentId, string $reference, string $reason): void
    {
        abort_unless($manager->role === 'manager', 403);
        $this->sql->transaction(function () use ($manager, $paymentId, $reference, $reason): void {
            abort_unless(User::query()->whereKey($manager->id)->where('role', 'manager')->where('is_active', true)->exists(), 403);
            $payment = DB::table('gym_online_payments')->where('id', $paymentId)->lockForUpdate()->first();
            abort_unless($payment, 404);
            if ($payment->status !== 'review' || $payment->review_resolution !== null
                || GymRefund::query()->where('source_type', 'online_payment')->where('source_id', $paymentId)->exists()) {
                throw ValidationException::withMessages(['payment' => 'Giao dịch không còn chờ hoàn hoặc đã được ghi nhận.']);
            }
            GymRefund::query()->create(['source_type' => 'online_payment', 'source_id' => $paymentId,
                'target_type' => $payment->target_type, 'target_id' => $payment->target_id, 'amount' => $payment->amount,
                'method' => 'vnpay', 'reference' => $reference, 'reason' => $reason, 'recorded_by' => $manager->id,
                'token' => (string) Str::uuid(), 'refunded_at' => now()]);
        });
    }
}
