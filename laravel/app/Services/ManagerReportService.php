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
     *     receipts_by_method: array<string, int>,
     *     orders: Collection<int, MembershipOrder>,
     *     payments: Collection<int, OrderPayment>,
     *     invoice_receipts: Collection<int, object>,
     *     online_payments: Collection<int, object>
     * }
     */
    public function report(?string $fromDate, ?string $toDate): array
    {
        $timezone = config('app.timezone');
        $from = $fromDate === null
            ? Carbon::now($timezone)->startOfMonth()
            : Carbon::createFromFormat('!Y-m-d', $fromDate, $timezone);
        $to = $toDate === null
            ? Carbon::now($timezone)->endOfMonth()->startOfDay()
            : Carbon::createFromFormat('!Y-m-d', $toDate, $timezone);
        $toExclusive = $to->copy()->addDay()->startOfDay();

        $orders = MembershipOrder::query()
            ->whereNull('sql_invoice_id')
            ->with(['user', 'gymPackage', 'confirmedBy', 'payments.receiver'])
            ->withSum('payments as total_received', 'amount')
            ->selectSub(DB::table('gym_online_payments')->selectRaw('COALESCE(SUM(amount), 0)')
                ->whereColumn('target_id', 'membership_orders.id')->where('target_type', 'membership_order')->where('status', 'succeeded'), 'online_received')
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
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $counts = MembershipOrder::query()
            ->whereNull('sql_invoice_id')
            ->selectRaw('status, COUNT(*) as total')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $toExclusive)
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $legacyDates = DB::table('membership_orders')->select('sql_invoice_id')->selectRaw('MAX(paid_at) AS paid_at')
            ->whereNotNull('sql_invoice_id')->groupBy('sql_invoice_id');
        $invoiceBase = DB::table('HOADON as h')->join('KHACHHANG as k', 'k.MAKH', '=', 'h.MAKH')
            ->leftJoin('gym_fulfillments as f', 'f.invoice_id', '=', 'h.MAHD')
            ->leftJoinSub($legacyDates, 'legacy_dates', 'legacy_dates.sql_invoice_id', '=', 'h.MAHD');
        $confirmedAt = 'COALESCE(f.paid_at, legacy_dates.paid_at, h.NGAYLAP)';
        $invoices = (clone $invoiceBase)->select('h.*', 'k.TENKH', 'k.EMAIL', 'k.SDT', 'f.shipping_fee')
            ->selectRaw($confirmedAt.' AS confirmed_at')
            ->selectSub(DB::table('gym_invoice_lines')->selectRaw("GROUP_CONCAT(name SEPARATOR ', ')")->whereColumn('invoice_id', 'h.MAHD'), 'contents')
            ->selectSub(DB::table('gym_receipts')->selectRaw('COALESCE(SUM(amount), 0)')->whereColumn('invoice_id', 'h.MAHD'), 'counter_received')
            ->selectSub(DB::table('gym_online_payments')->selectRaw('COALESCE(SUM(amount), 0)')->where('target_type', 'invoice')->where('status', 'succeeded')->whereColumn('target_id', 'h.MAHD'), 'online_received')
            ->selectSub(DB::table('order_payments as p')->join('membership_orders as o', 'o.id', '=', 'p.membership_order_id')->selectRaw('COALESCE(SUM(p.amount), 0)')->whereColumn('o.sql_invoice_id', 'h.MAHD'), 'legacy_received')
            ->selectSub(DB::table('gym_online_payments as p')->join('membership_orders as o', 'o.id', '=', 'p.target_id')->selectRaw('COALESCE(SUM(p.amount), 0)')->where('p.target_type', 'membership_order')->where('p.status', 'succeeded')->whereColumn('o.sql_invoice_id', 'h.MAHD'), 'legacy_online_received')
            ->where(function ($query) use ($from, $toExclusive, $confirmedAt): void {
                $query->where(function ($created) use ($from, $toExclusive): void {
                    $created->where('h.NGAYLAP', '>=', $from)->where('h.NGAYLAP', '<', $toExclusive);
                })->orWhere(function ($paid) use ($from, $toExclusive, $confirmedAt): void {
                    $paid->where('h.TRANGTHAI', 'Đã thanh toán')->whereRaw($confirmedAt.' >= ? AND '.$confirmedAt.' < ?', [$from, $toExclusive]);
                });
            })->orderByDesc('h.NGAYLAP')->orderByDesc('h.MAHD')->get();
        $invoiceCounts = (clone $invoiceBase)->where('h.NGAYLAP', '>=', $from)->where('h.NGAYLAP', '<', $toExclusive)
            ->selectRaw('h.TRANGTHAI, COUNT(*) AS total')->groupBy('h.TRANGTHAI')->pluck('total', 'TRANGTHAI');
        foreach (['pending' => 'Chưa thanh toán', 'paid' => 'Đã thanh toán', 'cancelled' => 'Hủy'] as $state => $label) {
            $counts[$state] = (int) ($counts[$state] ?? 0) + (int) ($invoiceCounts[$label] ?? 0);
        }
        $invoiceRevenue = (int) (clone $invoiceBase)->where('h.TRANGTHAI', 'Đã thanh toán')
            ->whereRaw($confirmedAt.' >= ? AND '.$confirmedAt.' < ?', [$from, $toExclusive])->sum('h.THANHTIEN');

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
            ->where('p.target_type', 'membership_order')->where('p.status', 'succeeded')
            ->where('p.paid_at', '>=', $from)->where('p.paid_at', '<', $toExclusive)
            ->select('p.*', 'u.name as customer_name')
            ->get();
        $onlineInvoicePayments = DB::table('gym_online_payments as p')
            ->join('HOADON as h', 'h.MAHD', '=', 'p.target_id')
            ->join('KHACHHANG as k', 'k.MAKH', '=', 'h.MAKH')
            ->where('p.target_type', 'invoice')->where('p.status', 'succeeded')
            ->where('p.paid_at', '>=', $from)->where('p.paid_at', '<', $toExclusive)
            ->select('p.*', 'k.TENKH as customer_name')
            ->get();
        $onlinePayments = $onlineMembershipPayments->concat($onlineInvoicePayments)->sortBy('paid_at')->values();
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
                MembershipOrder::STATUS_PENDING => (int) ($counts[MembershipOrder::STATUS_PENDING] ?? 0),
                MembershipOrder::STATUS_PAID => (int) ($counts[MembershipOrder::STATUS_PAID] ?? 0),
                MembershipOrder::STATUS_CANCELLED => (int) ($counts[MembershipOrder::STATUS_CANCELLED] ?? 0),
            ],
            'confirmed_revenue' => (int) MembershipOrder::query()
                ->whereNull('sql_invoice_id')
                ->where('status', MembershipOrder::STATUS_PAID)
                ->where('paid_at', '>=', $from)
                ->where('paid_at', '<', $toExclusive)
                ->sum('amount') + $invoiceRevenue,
            'receipts_total' => (int) $payments->sum('amount') + (int) $invoiceReceipts->sum('amount') + (int) $onlinePayments->sum('amount'),
            'receipts_by_method' => [
                'cash' => (int) ($receiptsByMethod['cash'] ?? 0),
                'bank_transfer' => (int) ($receiptsByMethod['bank_transfer'] ?? 0),
                'vnpay' => (int) $onlinePayments->sum('amount'),
            ],
            'orders' => $orders,
            'invoices' => $invoices,
            'payments' => $payments,
            'invoice_receipts' => $invoiceReceipts,
            'online_payments' => $onlinePayments,
        ];
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
            ['Danh sách đơn', 'Đơn tạo hoặc được xác nhận trong kỳ'],
        ], 1);
        $summarySheet->getStyle('A1:B1')->getFont()->setBold(true);
        $summarySheet->getColumnDimension('A')->setWidth(34);
        $summarySheet->getColumnDimension('B')->setWidth(28);
        $summarySheet->getStyle('B7:B11')->getNumberFormat()->setFormatCode('#,##0 "VNĐ"');

        $orderSheet = $spreadsheet->createSheet();
        $orderSheet->setTitle('Đơn hàng');
        $this->writeRows($orderSheet, [[
            'Mã đơn', 'Hội viên', 'Email', 'Số điện thoại', 'Gói tập', 'Ngày tạo', 'Ngày xác nhận',
            'Trạng thái', 'Giá trị đơn (VNĐ)', 'Đã nhận đến nay (VNĐ)', 'Còn phải thu (VNĐ)',
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
                max(0, (int) $order->amount - $received),
            ]]);
        }
        foreach ($report['invoices'] as $invoice) {
            $received = (int) $invoice->counter_received + (int) $invoice->online_received + (int) $invoice->legacy_received + (int) $invoice->legacy_online_received;
            $this->writeRows($orderSheet, [[
                'HD-'.$invoice->MAHD, $invoice->TENKH, $invoice->EMAIL ?? '', $invoice->SDT ?? '',
                $invoice->contents ?: 'Hóa đơn SQL #'.$invoice->MAHD,
                Carbon::parse($invoice->NGAYLAP)->format('d/m/Y H:i'),
                $invoice->TRANGTHAI === 'Đã thanh toán' ? Carbon::parse($invoice->confirmed_at)->format('d/m/Y H:i') : '',
                $invoice->TRANGTHAI, (int) $invoice->THANHTIEN, $received,
                $invoice->TRANGTHAI === 'Đã thanh toán' ? 0 : max(0, (int) $invoice->THANHTIEN - $received),
            ]]);
        }
        $this->formatDataSheet($orderSheet, 'K');

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
