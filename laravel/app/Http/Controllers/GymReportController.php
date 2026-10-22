<?php

namespace App\Http\Controllers;

use App\Services\GymPortalService;
use App\Services\ManagerReportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GymReportController extends Controller
{
    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d',
        ]);
        $fromValue = $data['from_date'] ?? $data['from'] ?? null;
        $toValue = $data['to_date'] ?? $data['to'] ?? null;
        $from = CarbonImmutable::parse($fromValue ?? now(config('app.timezone'))->startOfMonth()->toDateString(), config('app.timezone'))->startOfDay();
        $to = CarbonImmutable::parse($toValue ?? now(config('app.timezone'))->toDateString(), config('app.timezone'))->endOfDay();
        if ($to->lt($from) || $from->diffInDays($to) > 366) {
            $field = isset($data['to_date']) ? 'to_date' : 'to';
            throw ValidationException::withMessages([$field => 'Chọn khoảng ngày hợp lệ, tối đa 366 ngày.']);
        }

        return [$from, $to];
    }

    private function invoices(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('HOADON as h')->leftJoin('gym_fulfillments as f', 'f.invoice_id', '=', 'h.MAHD')->whereBetween('h.NGAYLAP', [$from, $to]);
    }

    public function index(Request $request, ManagerReportService $managerReports): View
    {
        [$from, $to] = $this->range($request);
        $report = $managerReports->report($from->toDateString(), $to->toDateString());
        $all = $this->invoices($from, $to);
        $paid = (clone $all)->where('h.TRANGTHAI', 'Đã thanh toán');
        $kind = "CASE WHEN d.MASP IS NOT NULL THEN 'product' WHEN d.MADKGT IS NOT NULL THEN 'package' WHEN d.MADKLOP IS NOT NULL THEN 'class' ELSE 'other' END";
        $lines = DB::table('CHITIETHOADON as d')->join('HOADON as h', 'h.MAHD', '=', 'd.MAHD')->whereBetween('h.NGAYLAP', [$from, $to])->where('h.TRANGTHAI', 'Đã thanh toán');
        $cash = (int) DB::table('gym_receipts')->whereBetween('created_at', [$from, $to])->sum('amount')
            + (int) DB::table('order_payments')->whereBetween('received_at', [$from, $to])->sum('amount');

        return view('manager.reports.index', ['from' => $from, 'to' => $to, 'report' => $report,
            'revenue' => (int) (clone $paid)->sum('h.THANHTIEN'), 'shipping' => (int) (clone $paid)->sum('f.shipping_fee'),
            'count' => (clone $paid)->count(), 'cash' => $cash, 'pending' => (clone $all)->where('h.TRANGTHAI', 'Chưa thanh toán')->count(),
            'daily' => (clone $paid)->selectRaw('DATE(h.NGAYLAP) AS day, SUM(h.THANHTIEN) AS total, COUNT(*) AS quantity')->groupByRaw('DATE(h.NGAYLAP)')->orderBy('day')->get(),
            'byKind' => (clone $lines)->selectRaw($kind.' AS kind, SUM(d.SOLUONG * d.DONGIA) AS gross')->groupByRaw($kind)->get(),
            'top' => (clone $lines)->join('SANPHAM as p', 'p.MASP', '=', 'd.MASP')->select('p.MASP', 'p.TENSP')->selectRaw('SUM(d.SOLUONG) AS quantity, SUM(d.SOLUONG * d.DONGIA) AS gross')->groupBy('p.MASP', 'p.TENSP')->orderByDesc('quantity')->limit(10)->get(),
            'visits' => DB::table('CHECKIN')->whereBetween('THOIGIANVAO', [$from, $to])->selectRaw('DATE(THOIGIANVAO) AS day, COUNT(*) AS quantity')->groupByRaw('DATE(THOIGIANVAO)')->orderBy('day')->get(),
            'expiring' => DB::table('DANGKYGOITAP as d')->join('KHACHHANG as k', 'k.MAKH', '=', 'd.MAKH')->join('GOITAP as g', 'g.MAGOITAP', '=', 'd.MAGOITAP')->where('d.TRANGTHAI', 'Còn hiệu lực')->whereBetween('d.NGAYKETTHUC', [now(), now()->addDays(7)])->select('d.*', 'k.TENKH', 'g.TENGOI')->orderBy('d.NGAYKETTHUC')->limit(50)->get(),
            'lowStock' => DB::table('SANPHAM')->where('SOLUONGTON', '<=', 5)->orderBy('SOLUONGTON')->limit(50)->get(),
            'incomplete' => (clone $paid)->whereIn('h.MAHD', app(GymPortalService::class)->unreconciledInvoices()->select('h.MAHD'))->count(),
        ]);
    }

    public function export(Request $request, ManagerReportService $managerReports): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $report = $managerReports->report($from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($report): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Mã đơn', 'Ngày lập', 'Khách hàng', 'Email', 'Số điện thoại', 'Sản phẩm/dịch vụ', 'Ngày xác nhận', 'Trạng thái', 'Giá trị đơn', 'Đã nhận', 'Còn phải thu'], ',', '"', '');
            foreach ($report['orders'] as $order) {
                $received = (int) ($order->total_received ?? 0) + (int) ($order->online_received ?? 0);
                $row = [
                    'GÓI-'.$order->id,
                    $order->created_at?->format('d/m/Y H:i'),
                    $order->user->name,
                    $order->user->email,
                    $order->user->phone,
                    $order->gymPackage->name,
                    $order->paid_at?->format('d/m/Y H:i') ?? '',
                    match ($order->status) {
                        'paid' => 'Đã thanh toán',
                        'cancelled' => 'Đã hủy',
                        default => 'Chờ thanh toán',
                    },
                    (int) $order->amount,
                    $received,
                    max(0, (int) $order->amount - $received),
                ];
                $values = array_map(fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@\-]/u', $value) ? "'".$value : $value, $row);
                fputcsv($stream, $values, ',', '"', '');
            }
            foreach ($report['invoice_orders'] as $order) {
                $received = (int) ($order->total_received ?? 0) + (int) ($order->online_received ?? 0);
                $row = [
                    'HD-'.$order->id,
                    CarbonImmutable::parse($order->created_at)->format('d/m/Y H:i'),
                    $order->customer_name,
                    $order->customer_email ?? '',
                    $order->customer_phone ?? '',
                    $order->item_name,
                    $order->confirmed_at ? CarbonImmutable::parse($order->confirmed_at)->format('d/m/Y H:i') : '',
                    match ($order->status) {
                        'Đã thanh toán' => 'Đã thanh toán',
                        'Hủy' => 'Đã hủy',
                        default => 'Chờ thanh toán',
                    },
                    (int) $order->amount,
                    $received,
                    max(0, (int) $order->amount - $received),
                ];
                $values = array_map(fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@\-]/u', $value) ? "'".$value : $value, $row);
                fputcsv($stream, $values, ',', '"', '');
            }
            fclose($stream);
        }, 'bao-cao-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
