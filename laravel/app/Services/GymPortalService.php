<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\GymMessageNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class GymPortalService
{
    public function __construct(private OriginalGymSql $sql) {}

    public function settings(): object
    {
        $settings = DB::table('gym_settings')
            ->select('*')
            ->selectRaw('gym_name as name, opening_hours as hours, bank_account_name as bank_holder')
            ->where('id', 1)
            ->firstOrFail();
        $settings->faq_items = is_array($settings->faq_items)
            ? $settings->faq_items
            : (json_decode($settings->faq_items ?? '[]', true) ?: []);

        return $settings;
    }

    public function unreconciledInvoices(): Builder
    {
        $detailTotal = 'COALESCE((SELECT SUM(l.quantity * l.unit_price) FROM gym_invoice_lines l WHERE l.invoice_id = h.MAHD), (SELECT SUM(d.SOLUONG * d.DONGIA) FROM CHITIETHOADON d WHERE d.MAHD = h.MAHD), 0)';

        return DB::table('HOADON as h')->leftJoin('gym_fulfillments as f', 'f.invoice_id', '=', 'h.MAHD')->where(function ($query) use ($detailTotal): void {
            $query->where(function ($empty): void {
                $empty->whereNotExists(fn ($q) => $q->selectRaw('1')->from('CHITIETHOADON as d')->whereColumn('d.MAHD', 'h.MAHD'))
                    ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('gym_invoice_lines as l')->whereColumn('l.invoice_id', 'h.MAHD'));
            })->orWhereRaw($detailTotal.' <> COALESCE(h.TONGTIEN, 0)')
                ->orWhereRaw('COALESCE(h.THANHTIEN, 0) <> COALESCE(h.TONGTIEN, 0) - COALESCE(h.GIAMGIA, 0) + COALESCE(f.shipping_fee, 0)');
        });
    }

    public function message(User $user, string $key, string $title, string $body, string $path): void
    {
        DB::table('gym_messages')->insertOrIgnore(['user_id' => $user->id, 'event_key' => $user->id.':'.$key,
            'title' => $title, 'body' => $body, 'path' => $path, 'created_at' => now(),
            'email_state' => $this->settings()->email_enabled ? 'pending' : 'disabled']);
    }

    public function invoiceMessage(int $invoice, string $event, string $title, string $body): void
    {
        $customer = DB::table('HOADON')->where('MAHD', $invoice)->value('MAKH');
        $user = User::query()->where('sql_customer_id', $customer)->first();
        if ($user) {
            $this->message($user, 'invoice:'.$invoice.':'.$event, $title, $body, route('gym.invoices.show', $invoice, absolute: false));
        }
    }

    public function trainingMessage(int $customer, int $employee, string $key, string $title, string $body): void
    {
        foreach (User::query()->where('sql_customer_id', $customer)->orWhere('sql_employee_id', $employee)->get() as $user) {
            $this->message($user, $key, $title, $body, route('gym.calendar', absolute: false));
        }
    }

    public function expireOrders(): int
    {
        return $this->sql->transaction(function (): int {
            $orders = DB::table('gym_fulfillments as f')->join('HOADON as h', 'h.MAHD', '=', 'f.invoice_id')
                ->where('h.TRANGTHAI', 'Chưa thanh toán')->whereNotNull('f.expires_at')->where('f.expires_at', '<=', now())
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('gym_receipts as r')->whereColumn('r.invoice_id', 'f.invoice_id'))
                ->select('f.invoice_id')->get();
            foreach ($orders as $order) {
                DB::table('HOADON')->where('MAHD', $order->invoice_id)->update(['TRANGTHAI' => 'Hủy']);
                DB::table('gym_fulfillments')->where('invoice_id', $order->invoice_id)->update(['state' => 'expired', 'updated_at' => now()]);
                $this->invoiceMessage((int) $order->invoice_id, 'expired', 'Đơn hàng đã hết hạn thanh toán', 'Đơn #'.$order->invoice_id.' đã hủy vì quá hạn và chưa nhận tiền. Hàng/chỗ đã được giải phóng; bạn có thể đặt đơn mới.');
                $this->sql->audit('Hủy đơn quá hạn chưa thu tiền', 'HOADON:'.$order->invoice_id);
            }

            return $orders->count();
        });
    }

    public function reminders(): void
    {
        $day = now()->addDays((int) $this->settings()->reminder_days)->toDateString();
        foreach (DB::table('DANGKYGOITAP')->where('TRANGTHAI', 'Còn hiệu lực')->whereDate('NGAYKETTHUC', $day)->get() as $row) {
            $user = User::query()->where('sql_customer_id', $row->MAKH)->first();
            if ($user) {
                $this->message($user, 'membership:'.$row->MADKGT.':'.$day, 'Gói tập sắp hết hạn', 'Bạn có thể tập đến hết ngày '.CarbonImmutable::parse($row->NGAYKETTHUC)->format('d/m/Y').'. Hãy chọn gói tiếp nối nếu muốn tiếp tục.', route('my.memberships.index', absolute: false));
            }
        }
        $orders = DB::table('gym_fulfillments as f')->join('HOADON as h', 'h.MAHD', '=', 'f.invoice_id')->where('h.TRANGTHAI', 'Chưa thanh toán')
            ->whereBetween('f.expires_at', [now(), now()->addHour()])->select('f.invoice_id', 'f.expires_at')->get();
        foreach ($orders as $row) {
            $this->invoiceMessage((int) $row->invoice_id, 'due', 'Đơn hàng sắp hết hạn thanh toán', 'Đơn #'.$row->invoice_id.' có hạn thanh toán '.CarbonImmutable::parse($row->expires_at)->format('H:i d/m/Y').'. Nếu đã chuyển khoản, hãy liên hệ quầy để đối chiếu.');
        }
        $pt = DB::table('LICHTAPPT as s')->join('DANGKYPT as r', 'r.MADKPT', '=', 's.MADKPT')->where('r.TRANGTHAI', 'Còn hiệu lực')->where('s.TRANGTHAI', 'Chưa tập')->whereBetween('s.GIOBATDAU', [now(), now()->addDay()])->select('s.*', 'r.MAKH', 'r.MANV')->get();
        foreach ($pt as $row) {
            foreach (User::query()->where('sql_customer_id', $row->MAKH)->orWhere('sql_employee_id', $row->MANV)->get() as $user) {
                $this->message($user, 'pt:'.$row->MALICHPT.':'.$row->GIOBATDAU, 'Nhắc lịch PT', 'Bạn có buổi PT lúc '.CarbonImmutable::parse($row->GIOBATDAU)->format('H:i d/m/Y').'.', route('gym.calendar', absolute: false));
            }
        }
        $classes = DB::table('LICHLOP as s')->join('LOPHOC as l', 'l.MALOP', '=', 's.MALOP')->where('s.TRANGTHAI', 'Chưa diễn ra')->whereBetween('s.GIOBATDAU', [now(), now()->addDay()])->select('s.*', 'l.TENLOP')->get();
        foreach ($classes as $row) {
            $customers = DB::table('DANGKYLOP')->where('MALOP', $row->MALOP)->where('TRANGTHAI', 'Còn hiệu lực')->pluck('MAKH');
            foreach (User::query()->whereIn('sql_customer_id', $customers)->orWhere('sql_employee_id', $row->MANV)->get() as $user) {
                $this->message($user, 'class:'.$row->MALICHLOP.':'.$row->GIOBATDAU, 'Nhắc lịch lớp học', $row->TENLOP.' bắt đầu lúc '.CarbonImmutable::parse($row->GIOBATDAU)->format('H:i d/m/Y').'.', route('gym.calendar', absolute: false));
            }
        }
    }

    public function deliverMessages(): int
    {
        if (! $this->settings()->email_enabled) {
            return 0;
        }
        $sent = 0;
        foreach (DB::table('gym_messages')->whereIn('email_state', ['pending', 'failed'])->where('attempts', '<', 3)->orderBy('id')->limit(20)->get() as $row) {
            $user = User::query()->whereKey($row->user_id)->where('is_active', true)->first();
            if (! $user?->email) {
                DB::table('gym_messages')->where('id', $row->id)->update(['email_state' => 'skipped']);

                continue;
            }
            DB::table('gym_messages')->where('id', $row->id)->increment('attempts');
            try {
                $user->notify(new GymMessageNotification($row->title, $row->body, $row->path));
                $state = in_array(config('mail.default'), ['log', 'array'], true) ? 'logged' : 'sent';
                DB::table('gym_messages')->where('id', $row->id)->update(['email_state' => $state, 'email_sent_at' => now(), 'last_error' => null]);
                $sent++;
            } catch (\Throwable $exception) {
                DB::table('gym_messages')->where('id', $row->id)->update(['email_state' => 'failed', 'last_error' => 'Chưa gửi được email. Kiểm tra cấu hình và kết nối SMTP.']);
            }
        }

        return $sent;
    }
}
