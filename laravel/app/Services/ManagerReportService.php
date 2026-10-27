<?php

namespace App\Services;

use App\Models\MembershipOrder;
use App\Models\OrderPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ManagerReportService
{
    /**
     * @return array{
     *     from_date: string,
     *     to_date: string,
     *     order_counts: array<string, int>,
     *     confirmed_revenue: int,
     *     receipts_total: int,
     *     refunds_total: int,
     *     receipts_net: int,
     *     payment_review_total: int,
     *     receipts_by_method: array<string, int>
     * }
     */
    public function summary(?string $fromDate, ?string $toDate): array
    {
        [$from, $to, $toExclusive] = $this->reportPeriod($fromDate, $toDate);

        $orderCounts = MembershipOrder::query()
            ->selectRaw('status, COUNT(*) as total')
            ->whereNull('sql_invoice_id')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $toExclusive)
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
        $invoiceCounts = DB::table('HOADON')
            ->selectRaw('TRANGTHAI as status, COUNT(*) as total')
            ->where('NGAYLAP', '>=', $from)
            ->where('NGAYLAP', '<', $toExclusive)
            ->groupBy('TRANGTHAI')
            ->pluck('total', 'status')
            ->all();

        $legacyOrderDates = DB::table('membership_orders as linked_orders')
            ->select('sql_invoice_id')
            ->selectRaw('MAX(paid_at) as legacy_paid_at')
            ->whereNotNull('sql_invoice_id')
            ->groupBy('sql_invoice_id');
        $invoiceRevenue = DB::table('HOADON as h')
            ->leftJoin('gym_fulfillments as f', 'f.invoice_id', '=', 'h.MAHD')
            ->leftJoinSub($legacyOrderDates, 'legacy_order_dates', 'legacy_order_dates.sql_invoice_id', '=', 'h.MAHD')
            ->where('h.TRANGTHAI', 'Đã thanh toán')
            ->whereRaw('COALESCE(f.paid_at, legacy_order_dates.legacy_paid_at, h.NGAYLAP) >= ?', [$from])
            ->whereRaw('COALESCE(f.paid_at, legacy_order_dates.legacy_paid_at, h.NGAYLAP) < ?', [$toExclusive])
            ->sum('h.THANHTIEN');
        $membershipRevenue = MembershipOrder::query()
            ->whereNull('sql_invoice_id')
            ->where('status', MembershipOrder::STATUS_PAID)
            ->where('paid_at', '>=', $from)
            ->where('paid_at', '<', $toExclusive)
            ->sum('amount');

        $orderPaymentsByMethod = DB::table('order_payments')
            ->where('received_at', '>=', $from)
            ->where('received_at', '<', $toExclusive)
            ->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');
        $invoiceReceiptsByMethod = DB::table('gym_receipts')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $toExclusive)
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method');
        $onlinePayments = DB::table('gym_online_payments as p')
            ->whereIn('p.status', ['succeeded', 'review', 'refunded'])
            ->where('p.paid_at', '>=', $from)
            ->where('p.paid_at', '<', $toExclusive)
            ->where(function ($query): void {
                $query->where(function ($membershipPayments): void {
                    $membershipPayments->where('p.target_type', 'membership_order')
                        ->whereExists(function ($target): void {
                            $target->selectRaw('1')->from('membership_orders as o')->whereColumn('o.id', 'p.target_id');
                        });
                })->orWhere(function ($invoicePayments): void {
                    $invoicePayments->where('p.target_type', 'invoice')
                        ->whereExists(function ($target): void {
                            $target->selectRaw('1')->from('HOADON as h')->whereColumn('h.MAHD', 'p.target_id');
                        });
                });
            })
            ->selectRaw('COALESCE(SUM(p.amount), 0) as total, COALESCE(SUM(CASE WHEN p.status = ? THEN p.amount ELSE 0 END), 0) as review_total', ['review'])
            ->first();
        $receiptsTotal = (int) $orderPaymentsByMethod->sum() + (int) $invoiceReceiptsByMethod->sum() + (int) ($onlinePayments->total ?? 0);
        $refundsTotal = (int) DB::table('gym_refunds')
            ->where('refunded_at', '>=', $from)
            ->where('refunded_at', '<', $toExclusive)
            ->sum('amount');

        return [
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'order_counts' => [
                MembershipOrder::STATUS_PENDING => (int) ($orderCounts[MembershipOrder::STATUS_PENDING] ?? 0) + (int) ($invoiceCounts['Chưa thanh toán'] ?? 0),
                MembershipOrder::STATUS_PAID => (int) ($orderCounts[MembershipOrder::STATUS_PAID] ?? 0) + (int) ($invoiceCounts['Đã thanh toán'] ?? 0),
                MembershipOrder::STATUS_CANCELLED => (int) ($orderCounts[MembershipOrder::STATUS_CANCELLED] ?? 0) + (int) ($invoiceCounts['Hủy'] ?? 0),
            ],
            'confirmed_revenue' => (int) $membershipRevenue + (int) $invoiceRevenue,
            'receipts_total' => $receiptsTotal,
            'refunds_total' => $refundsTotal,
            'receipts_net' => $receiptsTotal - $refundsTotal,
            'payment_review_total' => (int) ($onlinePayments->review_total ?? 0),
            'receipts_by_method' => [
                'cash' => (int) ($orderPaymentsByMethod['cash'] ?? 0) + (int) ($invoiceReceiptsByMethod['cash'] ?? 0),
                'bank_transfer' => (int) ($orderPaymentsByMethod['bank_transfer'] ?? 0) + (int) ($invoiceReceiptsByMethod['bank_transfer'] ?? 0),
                'vnpay' => (int) ($onlinePayments->total ?? 0),
            ],
        ];
    }

    /**
     * @return array{
     *     from_date: string,
     *     to_date: string,
     *     order_counts: array<string, int>,
     *     confirmed_revenue: int,
     *     receipts_total: int,
     *     receipts_by_method: array<string, int>,
     *     orders: Collection<int, MembershipOrder>,
     *     invoice_orders: Collection<int, object>,
     *     payments: Collection<int, OrderPayment>,
     *     invoice_receipts: Collection<int, object>,
     *     online_payments: Collection<int, object>
     * }
     */
    public function report(?string $fromDate, ?string $toDate): array
    {
        [$from, $to, $toExclusive] = $this->reportPeriod($fromDate, $toDate);

        $orders = MembershipOrder::query()
            ->with(['user', 'gymPackage', 'confirmedBy', 'payments.receiver'])
            ->withSum('payments as total_received', 'amount')
            ->selectSub(DB::table('gym_online_payments')->selectRaw('COALESCE(SUM(amount), 0)')
                ->whereColumn('target_id', 'membership_orders.id')->where('target_type', 'membership_order')->whereIn('status', ['succeeded', 'refunded']), 'online_received')
            ->selectSub(DB::table('gym_refunds')->selectRaw('COALESCE(SUM(amount), 0)')
                ->whereColumn('target_id', 'membership_orders.id')->where('target_type', 'membership_order'), 'refunded_total')
            ->where(function (Builder $query) use ($from, $toExclusive): void {
                $query->where(function (Builder $createdQuery) use ($from, $toExclusive): void {
                    $createdQuery->where('created_at', '>=', $from)
                        ->where('created_at', '<', $toExclusive);
                })->orWhere(function (Builder $confirmedQuery) use ($from, $toExclusive): void {
                    $confirmedQuery->where('status', MembershipOrder::STATUS_PAID)
                        ->where('paid_at', '>=', $from)
                        ->where('paid_at', '<', $toExclusive);
                });
            })
            ->whereNull('sql_invoice_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
        foreach ($orders as $order) {
            $grossReceived = (int) ($order->total_received ?? 0) + (int) ($order->online_received ?? 0);
            $order->net_received = max(0, $grossReceived - (int) $order->refunded_total);
            $order->remaining_amount = $order->status === MembershipOrder::STATUS_CANCELLED
                ? 0
                : max(0, (int) $order->amount - $order->net_received);
        }

        $counts = MembershipOrder::query()
            ->selectRaw('status, COUNT(*) as total')
            ->whereNull('sql_invoice_id')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $toExclusive)
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $legacyOrderDates = DB::table('membership_orders as linked_orders')
            ->select('sql_invoice_id')
            ->selectRaw('MAX(paid_at) as legacy_paid_at')
            ->whereNotNull('sql_invoice_id')
            ->groupBy('sql_invoice_id');

        $invoiceOrders = DB::table('HOADON as h')
            ->join('KHACHHANG as k', 'k.MAKH', '=', 'h.MAKH')
            ->leftJoin('gym_fulfillments as f', 'f.invoice_id', '=', 'h.MAHD')
            ->leftJoinSub($legacyOrderDates, 'legacy_order_dates', 'legacy_order_dates.sql_invoice_id', '=', 'h.MAHD')
            ->where(function ($query) use ($from, $toExclusive): void {
                $query->where(function ($created) use ($from, $toExclusive): void {
                    $created->where('h.NGAYLAP', '>=', $from)->where('h.NGAYLAP', '<', $toExclusive);
                })->orWhere(function ($confirmed) use ($from, $toExclusive): void {
                    $confirmed->where('h.TRANGTHAI', 'Đã thanh toán')
                        ->whereRaw('COALESCE(f.paid_at, legacy_order_dates.legacy_paid_at, h.NGAYLAP) >= ?', [$from])
                        ->whereRaw('COALESCE(f.paid_at, legacy_order_dates.legacy_paid_at, h.NGAYLAP) < ?', [$toExclusive]);
                });
            })
            ->select('h.MAHD as id', 'k.TENKH as customer_name', 'k.EMAIL as customer_email', 'k.SDT as customer_phone', 'h.NGAYLAP as created_at', 'h.TRANGTHAI as status', 'h.THANHTIEN as amount', 'f.paid_at')
            ->selectRaw("CASE WHEN h.TRANGTHAI = 'Đã thanh toán' THEN COALESCE(f.paid_at, legacy_order_dates.legacy_paid_at, h.NGAYLAP) ELSE f.paid_at END as confirmed_at")
            ->selectRaw("COALESCE((SELECT l.name FROM gym_invoice_lines l WHERE l.invoice_id = h.MAHD ORDER BY l.id LIMIT 1), 'Hóa đơn') as item_name")
            ->selectSub(DB::table('gym_receipts')->selectRaw('COALESCE(SUM(amount), 0)')->whereColumn('invoice_id', 'h.MAHD'), 'total_received')
            ->selectSub(DB::table('order_payments')->selectRaw('COALESCE(SUM(amount), 0)')
                ->whereIn('membership_order_id', DB::table('membership_orders as linked_orders')->select('linked_orders.id')->whereColumn('linked_orders.sql_invoice_id', 'h.MAHD')), 'legacy_total_received')
            ->selectSub(DB::table('gym_online_payments')->selectRaw('COALESCE(SUM(amount), 0)')->whereColumn('target_id', 'h.MAHD')->where('target_type', 'invoice')->whereIn('status', ['succeeded', 'refunded']), 'online_gross_received')
            ->selectSub(DB::table('gym_online_payments')->selectRaw('COALESCE(SUM(amount), 0)')->where('target_type', 'membership_order')
                ->whereIn('target_id', DB::table('membership_orders as linked_orders')->select('linked_orders.id')->whereColumn('linked_orders.sql_invoice_id', 'h.MAHD'))
                ->whereIn('status', ['succeeded', 'refunded']), 'legacy_online_received')
            ->selectSub(DB::table('gym_refunds')->selectRaw('COALESCE(SUM(amount), 0)')->whereColumn('target_id', 'h.MAHD')->where('target_type', 'invoice'), 'refunded_total')
            ->selectSub(DB::table('gym_refunds')->selectRaw('COALESCE(SUM(amount), 0)')->where('target_type', 'membership_order')
                ->whereIn('target_id', DB::table('membership_orders as linked_orders')->select('linked_orders.id')->whereColumn('linked_orders.sql_invoice_id', 'h.MAHD')), 'legacy_refunded_total')
            ->orderByDesc('h.NGAYLAP')->orderByDesc('h.MAHD')->get();
        foreach ($invoiceOrders as $order) {
            $order->total_received = (int) $order->total_received + (int) $order->legacy_total_received;
            $order->online_received = (int) $order->online_gross_received + (int) $order->legacy_online_received;
            $order->refunded_total = (int) $order->refunded_total + (int) $order->legacy_refunded_total;
            $netReceived = max(0, $order->total_received + $order->online_received - $order->refunded_total);
            $order->net_received = $netReceived;
            $order->remaining_amount = $order->status === 'Hủy'
                ? 0
                : max(0, (int) $order->amount - $netReceived);
        }

        $invoiceCounts = DB::table('HOADON')->selectRaw('TRANGTHAI as status, COUNT(*) as total')
            ->where('NGAYLAP', '>=', $from)->where('NGAYLAP', '<', $toExclusive)
            ->groupBy('TRANGTHAI')->pluck('total', 'status')->all();

        $payments = OrderPayment::query()
            ->with(['order.user', 'receiver'])
            ->where('received_at', '>=', $from)
            ->where('received_at', '<', $toExclusive)
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
        $invoiceReceipts = DB::table('gym_receipts as r')
            ->join('HOADON as h', 'h.MAHD', '=', 'r.invoice_id')
            ->join('KHACHHANG as k', 'k.MAKH', '=', 'h.MAKH')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.created_at', '>=', $from)
            ->where('r.created_at', '<', $toExclusive)
            ->orderBy('r.created_at')->orderBy('r.id')
            ->select('r.id', 'r.invoice_id as target_id', 'r.amount', 'r.method as payment_method', 'r.reference', 'r.created_at as received_at', 'u.name as receiver_name', 'k.TENKH as customer_name')
            ->get();
        $onlineMembershipPayments = DB::table('gym_online_payments as p')
            ->join('membership_orders as o', 'o.id', '=', 'p.target_id')
            ->join('users as u', 'u.id', '=', 'o.user_id')
            ->where('p.target_type', 'membership_order')->whereIn('p.status', ['succeeded', 'review', 'refunded'])
            ->where('p.paid_at', '>=', $from)->where('p.paid_at', '<', $toExclusive)
            ->select('p.*', 'u.name as customer_name')
            ->get();
        $onlineInvoicePayments = DB::table('gym_online_payments as p')
            ->join('HOADON as h', 'h.MAHD', '=', 'p.target_id')
            ->join('KHACHHANG as k', 'k.MAKH', '=', 'h.MAKH')
            ->where('p.target_type', 'invoice')->whereIn('p.status', ['succeeded', 'review', 'refunded'])
            ->where('p.paid_at', '>=', $from)->where('p.paid_at', '<', $toExclusive)
            ->select('p.*', 'k.TENKH as customer_name')
            ->get();
        $onlinePayments = $onlineMembershipPayments->concat($onlineInvoicePayments)->sortBy('paid_at')->values();
        $refunds = DB::table('gym_refunds as r')->join('users as u', 'u.id', '=', 'r.recorded_by')
            ->where('r.refunded_at', '>=', $from)->where('r.refunded_at', '<', $toExclusive)
            ->select('r.*', 'u.name as recorder_name')->orderBy('r.refunded_at')->get();
        $refundsTotal = (int) $refunds->sum('amount');
        $receiptsTotal = (int) $payments->sum('amount') + (int) $invoiceReceipts->sum('amount') + (int) $onlinePayments->sum('amount');
        $paymentReviewTotal = (int) $onlinePayments->where('status', 'review')->sum('amount');
        $receiptsByMethod = $payments->groupBy('payment_method')
            ->map(fn (Collection $methodPayments): int => (int) $methodPayments->sum('amount'))
            ->all();
        foreach ($invoiceReceipts->groupBy('payment_method') as $method => $methodPayments) {
            $receiptsByMethod[$method] = (int) ($receiptsByMethod[$method] ?? 0) + (int) $methodPayments->sum('amount');
        }

        return [
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'order_counts' => [
                MembershipOrder::STATUS_PENDING => (int) ($counts[MembershipOrder::STATUS_PENDING] ?? 0) + (int) ($invoiceCounts['Chưa thanh toán'] ?? 0),
                MembershipOrder::STATUS_PAID => (int) ($counts[MembershipOrder::STATUS_PAID] ?? 0) + (int) ($invoiceCounts['Đã thanh toán'] ?? 0),
                MembershipOrder::STATUS_CANCELLED => (int) ($counts[MembershipOrder::STATUS_CANCELLED] ?? 0) + (int) ($invoiceCounts['Hủy'] ?? 0),
            ],
            'confirmed_revenue' => (int) MembershipOrder::query()
                ->whereNull('sql_invoice_id')
                ->where('status', MembershipOrder::STATUS_PAID)
                ->where('paid_at', '>=', $from)
                ->where('paid_at', '<', $toExclusive)
                ->sum('amount') + (int) DB::table('HOADON as h')->leftJoin('gym_fulfillments as f', 'f.invoice_id', '=', 'h.MAHD')
                ->leftJoinSub($legacyOrderDates, 'legacy_order_dates', 'legacy_order_dates.sql_invoice_id', '=', 'h.MAHD')
                ->where('h.TRANGTHAI', 'Đã thanh toán')
                ->whereRaw('COALESCE(f.paid_at, legacy_order_dates.legacy_paid_at, h.NGAYLAP) >= ?', [$from])
                ->whereRaw('COALESCE(f.paid_at, legacy_order_dates.legacy_paid_at, h.NGAYLAP) < ?', [$toExclusive])
                ->sum('h.THANHTIEN'),
            'receipts_total' => $receiptsTotal,
            'refunds_total' => $refundsTotal,
            'receipts_net' => $receiptsTotal - $refundsTotal,
            'payment_review_total' => $paymentReviewTotal,
            'refunds' => $refunds,
            'receipts_by_method' => [
                'cash' => (int) ($receiptsByMethod['cash'] ?? 0),
                'bank_transfer' => (int) ($receiptsByMethod['bank_transfer'] ?? 0),
                'vnpay' => (int) $onlinePayments->sum('amount'),
            ],
            'orders' => $orders,
            'invoice_orders' => $invoiceOrders,
            'payments' => $payments,
            'invoice_receipts' => $invoiceReceipts,
            'online_payments' => $onlinePayments,
        ];
    }

    /** @return array{Carbon, Carbon, Carbon} */
    private function reportPeriod(?string $fromDate, ?string $toDate): array
    {
        $timezone = config('app.timezone');
        $from = $fromDate === null
            ? Carbon::now($timezone)->startOfMonth()
            : Carbon::createFromFormat('!Y-m-d', $fromDate, $timezone);
        $to = $toDate === null
            ? Carbon::now($timezone)->endOfMonth()->startOfDay()
            : Carbon::createFromFormat('!Y-m-d', $toDate, $timezone);

        return [$from, $to, $to->copy()->addDay()->startOfDay()];
    }

    /** @param array<string, mixed> $report */
    public function writeXlsx(array $report): void
    {
        $spreadsheet = new Spreadsheet;
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Tổng quan');
        $this->writeRows($summarySheet, [
            ['Báo cáo The Gym', 'Giá trị'],
            ['Từ ngày', $report['from_date']],
            ['Đến ngày', $report['to_date']],
            ['Đơn chờ thanh toán', $report['order_counts'][MembershipOrder::STATUS_PENDING]],
            ['Đơn đã thanh toán', $report['order_counts'][MembershipOrder::STATUS_PAID]],
            ['Đơn đã hủy', $report['order_counts'][MembershipOrder::STATUS_CANCELLED]],
            ['Doanh thu đơn đã xác nhận', $report['confirmed_revenue']],
            ['Tiền thực nhận', $report['receipts_total']],
            ['Tiền mặt thực nhận', $report['receipts_by_method']['cash']],
            ['Chuyển khoản thực nhận', $report['receipts_by_method']['bank_transfer']],
            ['VNPay thực nhận', $report['receipts_by_method']['vnpay']],
            ['VNPay đang chờ đối soát (đã nhận)', $report['payment_review_total']],
            ['Tiền đã hoàn', $report['refunds_total']],
            ['Thu ròng trong kỳ', $report['receipts_net']],
            ['Danh sách đơn', 'Đơn tạo hoặc được xác nhận trong kỳ'],
        ], 1);
        $summarySheet->getStyle('A1:B1')->getFont()->setBold(true);
        $summarySheet->getColumnDimension('A')->setWidth(34);
        $summarySheet->getColumnDimension('B')->setWidth(28);
        $summarySheet->getStyle('B7:B14')->getNumberFormat()->setFormatCode('#,##0 "VNĐ"');

        $orderSheet = $spreadsheet->createSheet();
        $orderSheet->setTitle('Đơn hàng');
        $this->writeRows($orderSheet, [[
            'Mã đơn', 'Hội viên', 'Email', 'Số điện thoại', 'Sản phẩm/dịch vụ', 'Ngày tạo', 'Ngày xác nhận',
            'Trạng thái', 'Giá trị đơn (VNĐ)', 'Đã nhận gộp (VNĐ)', 'Đã hoàn (VNĐ)', 'Thu ròng (VNĐ)', 'Còn phải thu (VNĐ)',
        ]], 1);
        foreach ($report['orders'] as $order) {
            $received = (int) ($order->total_received ?? 0) + (int) ($order->online_received ?? 0);
            $this->writeRows($orderSheet, [[
                $order->id,
                $order->user->name,
                $order->user->email,
                $order->user->phone,
                $order->gymPackage->name,
                $order->created_at?->format('d/m/Y H:i'),
                $order->paid_at?->format('d/m/Y H:i') ?? '',
                match ($order->status) {
                    MembershipOrder::STATUS_PAID => 'Đã thanh toán',
                    MembershipOrder::STATUS_CANCELLED => 'Đã hủy',
                    default => 'Chờ thanh toán',
                },
                (int) $order->amount,
                $received,
                (int) $order->refunded_total,
                (int) $order->net_received,
                (int) $order->remaining_amount,
            ]]);
        }
        foreach ($report['invoice_orders'] as $order) {
            $received = (int) ($order->total_received ?? 0) + (int) ($order->online_received ?? 0);
            $this->writeRows($orderSheet, [[
                'HD-'.$order->id,
                $order->customer_name,
                $order->customer_email ?? '',
                $order->customer_phone ?? '',
                $order->item_name,
                Carbon::parse($order->created_at)->format('d/m/Y H:i'),
                $order->confirmed_at ? Carbon::parse($order->confirmed_at)->format('d/m/Y H:i') : '',
                match ($order->status) {
                    'Đã thanh toán' => 'Đã thanh toán',
                    'Hủy' => 'Đã hủy',
                    default => 'Chờ thanh toán',
                },
                (int) $order->amount,
                $received,
                (int) $order->refunded_total,
                (int) $order->net_received,
                (int) $order->remaining_amount,
            ]]);
        }
        $this->formatDataSheet($orderSheet, 'M');

        $paymentSheet = $spreadsheet->createSheet();
        $paymentSheet->setTitle('Khoản thu');
        $this->writeRows($paymentSheet, [[
            'Ngày nhận', 'Mã đơn', 'Hội viên', 'Phương thức', 'Số tiền (VNĐ)', 'Người ghi nhận', 'Mã tham chiếu',
        ]], 1);
        foreach ($report['payments'] as $payment) {
            $this->writeRows($paymentSheet, [[
                $payment->received_at->format('d/m/Y H:i'),
                $payment->membership_order_id,
                $payment->order->user->name,
                $payment->payment_method === 'cash' ? 'Tiền mặt' : 'Chuyển khoản',
                (int) $payment->amount,
                $payment->receiver->name,
                $payment->reference ?? '',
            ]]);
        }
        foreach ($report['invoice_receipts'] as $payment) {
            $this->writeRows($paymentSheet, [[
                Carbon::parse($payment->received_at)->format('d/m/Y H:i'),
                'HD-'.$payment->target_id,
                $payment->customer_name,
                $payment->payment_method === 'cash' ? 'Tiền mặt' : 'Chuyển khoản',
                (int) $payment->amount,
                $payment->receiver_name,
                $payment->reference ?? '',
            ]]);
        }
        foreach ($report['online_payments'] as $payment) {
            $this->writeRows($paymentSheet, [[
                Carbon::parse($payment->paid_at)->format('d/m/Y H:i'),
                $payment->target_type === 'invoice' ? 'HD-'.$payment->target_id : 'Đơn gói #'.$payment->target_id,
                $payment->customer_name,
                'VNPay',
                (int) $payment->amount,
                'Cổng thanh toán VNPay',
                $payment->provider_transaction_no ?: $payment->txn_ref,
            ]]);
        }
        foreach ($report['refunds'] as $refund) {
            $this->writeRows($paymentSheet, [[
                Carbon::parse($refund->refunded_at)->format('d/m/Y H:i'),
                $refund->target_type === 'invoice' ? 'HD-'.$refund->target_id : 'Đơn gói #'.$refund->target_id,
                '', 'Hoàn tiền', -(int) $refund->amount, $refund->recorder_name, $refund->reference,
            ]]);
        }
        $this->formatDataSheet($paymentSheet, 'G');

        (new Xlsx($spreadsheet))->save('php://output');
        $spreadsheet->disconnectWorksheets();
    }

    /** @param list<list<int|float|string>> $rows */
    private function writeRows(Worksheet $sheet, array $rows, ?int $startRow = null): void
    {
        $rowNumber = $startRow ?? $sheet->getHighestRow() + 1;

        foreach ($rows as $row) {
            foreach ($row as $columnOffset => $value) {
                $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($columnOffset + 1).$rowNumber);

                if (is_string($value)) {
                    $cell->setValueExplicit($value, DataType::TYPE_STRING);
                } else {
                    $cell->setValue($value);
                }
            }

            $rowNumber++;
        }
    }

    private function formatDataSheet(Worksheet $sheet, string $lastColumn): void
    {
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$lastColumn.$sheet->getHighestRow());

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
}
