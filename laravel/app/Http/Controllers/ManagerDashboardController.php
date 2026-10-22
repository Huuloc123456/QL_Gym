<?php

namespace App\Http\Controllers;

use App\Models\MembershipOrder;
use App\Models\User;
use App\Repositories\CheckInRepository;
use App\Repositories\GymPackageRepository;
use App\Repositories\MembershipRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ManagerDashboardController extends Controller
{
    public function index(
        CheckInRepository $checkIns,
        GymPackageRepository $packages,
        MembershipRepository $memberships,
    ): View {
        $today = now(config('app.timezone'))->toDateString();
        $invoices = DB::table('HOADON as h')->join('KHACHHANG as k', 'k.MAKH', '=', 'h.MAKH')
            ->selectRaw("'invoice' as source, h.MAHD as record_id, k.TENKH as customer_name, h.NGAYLAP as ordered_at, h.THANHTIEN as amount, h.TRANGTHAI as status, COALESCE((SELECT l.name FROM gym_invoice_lines l WHERE l.invoice_id = h.MAHD ORDER BY l.id LIMIT 1), 'Hóa đơn') as item_name");
        $packageOrders = DB::table('membership_orders as o')->join('users as u', 'u.id', '=', 'o.user_id')->join('gym_packages as p', 'p.id', '=', 'o.gym_package_id')
            ->whereNull('o.sql_invoice_id')
            ->selectRaw("'package_order' as source, o.id as record_id, u.name as customer_name, o.created_at as ordered_at, o.amount as amount, CASE o.status WHEN 'paid' THEN 'Đã thanh toán' WHEN 'cancelled' THEN 'Hủy' ELSE 'Chưa thanh toán' END as status, p.name as item_name");
        $recentOrders = DB::query()->fromSub($invoices->unionAll($packageOrders), 'recent_orders')
            ->orderByDesc('ordered_at')->orderByDesc('record_id')->limit(8)->get();

        return view('manager.dashboard', [
            'customerCount' => User::query()->where('role', 'customer')->count(),
            'activeMembershipCount' => $memberships->activeCountOn($today),
            'pendingOrderCount' => MembershipOrder::query()->whereNull('sql_invoice_id')->where('status', MembershipOrder::STATUS_PENDING)->count() + DB::table('HOADON')->where('TRANGTHAI', 'Chưa thanh toán')->count(),
            'paymentReviewCount' => DB::table('gym_online_payments')->where('status', 'review')->count(),
            'openVisitCount' => $checkIns->openCount(),
            'allInvoiceRevenue' => (int) DB::table('HOADON')->where('TRANGTHAI', 'Đã thanh toán')->sum('THANHTIEN') + (int) MembershipOrder::query()->whereNull('sql_invoice_id')->where('status', 'paid')->sum('amount'),
            'productCount' => DB::table('SANPHAM')->count(),
            'lowStockCount' => DB::table('SANPHAM')->where('SOLUONGTON', '<=', 5)->count(),
            'packages' => $packages->activePackages(),
            'recentOrders' => $recentOrders,
        ]);
    }
}
