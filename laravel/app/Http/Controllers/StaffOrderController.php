<?php

namespace App\Http\Controllers;

use App\Models\MembershipOrder;
use App\Repositories\MembershipOrderRepository;
use App\Services\MembershipOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffOrderController extends Controller
{
    public function index(Request $request, MembershipOrderRepository $orderRepository): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['pending', 'history', 'all', 'paid', 'cancelled'])],
            'q' => ['nullable', 'string', 'max:100'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);
        $filters['status'] ??= 'pending';
        $orders = $orderRepository->paginateForStaff($filters);

        return view('staff.orders.index', compact('orders', 'filters'));
    }

    public function recordPayment(Request $request, MembershipOrder $order, MembershipOrderService $service): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:'.$order->amount],
            'payment_method' => ['required', 'in:cash,bank_transfer'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $service->recordPayment(
            $order,
            $request->user(),
            (int) $validated['amount'],
            $validated['payment_method'],
            $validated['reference'] ?? null,
        );

        return redirect()->route('staff.orders.index')->with('success', 'Đã ghi nhận khoản tiền.');
    }

    public function confirm(Request $request, MembershipOrder $order, MembershipOrderService $service): RedirectResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
        ]);

        $service->confirm($order, $request->user(), $validated['start_date']);

        return redirect()->route('staff.orders.index')->with('success', 'Đã xác nhận thanh toán và kích hoạt gói tập.');
    }

    public function cancel(MembershipOrder $order, MembershipOrderService $service): RedirectResponse
    {
        $service->cancel($order);

        return redirect()->route('staff.orders.index')->with('success', 'Đơn đã được hủy.');
    }
}
