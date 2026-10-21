<?php

namespace App\Services;

use App\Models\MembershipOrder;
use App\Models\OrderPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
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
     *     payments: Collection<int, OrderPayment>
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
            ->with(['user', 'gymPackage', 'confirmedBy', 'payments.receiver'])
            ->withSum('payments as total_received', 'amount')
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
            ->selectRaw('status, COUNT(*) as total')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $toExclusive)
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $payments = OrderPayment::query()
            ->with(['order.user', 'receiver'])
            ->where('received_at', '>=', $from)
            ->where('received_at', '<', $toExclusive)
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
        $receiptsByMethod = $payments->groupBy('payment_method')
            ->map(fn (Collection $methodPayments): int => (int) $methodPayments->sum('amount'))
            ->all();

        return [
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'order_counts' => [
                MembershipOrder::STATUS_PENDING => (int) ($counts[MembershipOrder::STATUS_PENDING] ?? 0),
                MembershipOrder::STATUS_PAID => (int) ($counts[MembershipOrder::STATUS_PAID] ?? 0),
                MembershipOrder::STATUS_CANCELLED => (int) ($counts[MembershipOrder::STATUS_CANCELLED] ?? 0),
            ],
            'confirmed_revenue' => (int) MembershipOrder::query()
                ->where('status', MembershipOrder::STATUS_PAID)
                ->where('paid_at', '>=', $from)
                ->where('paid_at', '<', $toExclusive)
                ->sum('amount'),
            'receipts_total' => (int) $payments->sum('amount'),
            'receipts_by_method' => [
                'cash' => (int) ($receiptsByMethod['cash'] ?? 0),
                'bank_transfer' => (int) ($receiptsByMethod['bank_transfer'] ?? 0),
            ],
            'orders' => $orders,
            'payments' => $payments,
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
            ['Danh sách đơn', 'Đơn tạo hoặc được xác nhận trong kỳ'],
        ], 1);
        $summarySheet->getStyle('A1:B1')->getFont()->setBold(true);
        $summarySheet->getColumnDimension('A')->setWidth(34);
        $summarySheet->getColumnDimension('B')->setWidth(28);
        $summarySheet->getStyle('B7:B10')->getNumberFormat()->setFormatCode('#,##0 "VNĐ"');

        $orderSheet = $spreadsheet->createSheet();
        $orderSheet->setTitle('Đơn hàng');
        $this->writeRows($orderSheet, [[
            'Mã đơn', 'Hội viên', 'Email', 'Số điện thoại', 'Gói tập', 'Ngày tạo', 'Ngày xác nhận',
            'Trạng thái', 'Giá trị đơn (VNĐ)', 'Đã nhận đến nay (VNĐ)', 'Còn phải thu (VNĐ)',
        ]], 1);
        foreach ($report['orders'] as $order) {
            $received = (int) ($order->total_received ?? 0);
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
