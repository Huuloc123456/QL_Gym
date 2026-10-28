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
        $kind = "CASE WHEN d.MASP IS NOT NULL THEN 'product' WHEN d.MADKGT IS NOT NULL THEN 'package' WHEN d.MADKLOP IS NOT NULL THEN 'class' WHEN d.MADKPT IS NOT NULL THEN 'pt' ELSE 'other' END";
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

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $report = app(ManagerReportService::class)->report($from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($report): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Mã hóa đơn', 'Ngày lập', 'Khách hàng', 'Tiền hàng/dịch vụ', 'Giảm giá', 'Phí giao', 'Thành tiền', 'Trạng thái'], ',', '"', '');
            foreach ($report['invoices'] as $invoice) {
                $row = (object) ['id' => $invoice->MAHD, 'created_at' => $invoice->NGAYLAP, 'name' => $invoice->TENKH, 'gross' => $invoice->TONGTIEN, 'discount' => $invoice->GIAMGIA, 'shipping' => $invoice->shipping_fee ?? 0, 'total' => $invoice->THANHTIEN, 'state' => $invoice->TRANGTHAI];
                $values = array_map(fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@\-]/u', $value) ? "'".$value : $value, array_values((array) $row));
                fputcsv($stream, $values, ',', '"', '');
            }
            foreach ($report['orders'] as $order) {
                $values = ['LEGACY-'.$order->id, $order->created_at->format('Y-m-d H:i:s'), $order->user->name, $order->amount, 0, 0, $order->amount, ['paid' => 'Đã thanh toán', 'pending' => 'Chưa thanh toán', 'cancelled' => 'Hủy'][$order->status]];
                $values = array_map(fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@\-]/u', $value) ? "'".$value : $value, $values);
                fputcsv($stream, $values, ',', '"', '');
            }
            fclose($stream);
        }, 'bao-cao-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
