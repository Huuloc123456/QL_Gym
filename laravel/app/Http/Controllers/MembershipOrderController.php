<?php

namespace App\Http\Controllers;

use App\Models\MembershipOrder;
use App\Repositories\GymPackageRepository;
use App\Repositories\MembershipOrderRepository;
use App\Services\MembershipOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MembershipOrderController extends Controller
{
    public function index(Request $request, MembershipOrderRepository $orderRepository): View
    {
        $orders = $orderRepository->forCustomer($request->user())->get();

        return view('orders.index', ['orders' => $orders]);
    }

    public function show(Request $request, MembershipOrder $order, MembershipOrderRepository $orderRepository): View
    {
        $order = $orderRepository->findForCustomer($request->user(), $order->id);

        return view('orders.show', ['order' => $order]);
    }

    public function store(Request $request, MembershipOrderService $orders, GymPackageRepository $packages): RedirectResponse
    {
        $validated = $request->validate([
            'gym_package_id' => ['required', 'integer', 'exists:gym_packages,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'payment_method' => ['required', 'in:cash,bank_transfer'],
        ]);

        $package = $packages->findActiveOrFail((int) $validated['gym_package_id']);
        $orders->create(
            $request->user(),
            $package,
            $validated['start_date'],
            $validated['payment_method'],
        );

        return redirect()->route('my.orders.index')->with('success', 'Đơn đăng ký đã được tạo. Nhân viên sẽ xác nhận khi nhận đủ tiền.');
    }

    public function requestCancellation(Request $request, MembershipOrder $order, MembershipOrderService $orders, MembershipOrderRepository $orderRepository): RedirectResponse
    {
        $orders->requestCancellation($orderRepository->findForCustomer($request->user(), $order->id));

        return redirect()->route('my.orders.index')->with('success', 'Đã gửi yêu cầu hủy. Nhân viên sẽ kiểm tra các khoản đã nhận trước khi xử lý.');
    }
}
