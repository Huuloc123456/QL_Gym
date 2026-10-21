<?php

namespace App\Http\Controllers;

use App\Models\MembershipOrder;
use App\Models\User;
use App\Repositories\CheckInRepository;
use App\Repositories\GymPackageRepository;
use App\Repositories\MembershipOrderRepository;
use App\Repositories\MembershipRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ManagerDashboardController extends Controller
{
    public function index(
        CheckInRepository $checkIns,
        GymPackageRepository $packages,
        MembershipOrderRepository $orders,
        MembershipRepository $memberships,
    ): View {
        $today = now(config('app.timezone'))->toDateString();

        return view('manager.dashboard', [
            'customerCount' => User::query()->where('role', 'customer')->count(),
            'activeMembershipCount' => $memberships->activeCountOn($today),
            'pendingOrderCount' => $orders->pendingCount(),
            'openVisitCount' => $checkIns->openCount(),
            'paidRevenue' => $orders->paidRevenue(),
            'allInvoiceRevenue' => (int) DB::table('HOADON')->where('TRANGTHAI', 'Đã thanh toán')->sum('THANHTIEN') + (int) MembershipOrder::query()->whereNull('sql_invoice_id')->where('status', 'paid')->sum('amount'),
            'productCount' => DB::table('SANPHAM')->count(),
            'lowStockCount' => DB::table('SANPHAM')->where('SOLUONGTON', '<=', 5)->count(),
            'invoicePendingCount' => DB::table('HOADON')->where('TRANGTHAI', 'Chưa thanh toán')->count(),
            'packages' => $packages->activePackages(),
            'recentOrders' => $orders->recent(8),
        ]);
    }
}
