<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Repositories\CheckInRepository;
use App\Repositories\GymPackageRepository;
use App\Repositories\MembershipOrderRepository;
use App\Repositories\MembershipRepository;
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
            'packages' => $packages->activePackages(),
            'recentOrders' => $orders->recent(8),
        ]);
    }
}
