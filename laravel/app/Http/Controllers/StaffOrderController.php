<?php

namespace App\Http\Controllers;

use App\Models\MembershipOrder;
use App\Repositories\MembershipOrderRepository;
use App\Services\MembershipOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function show(MembershipOrder $order): View
    {
        $order->load(['user', 'gymPackage', 'membership', 'payments.receiver']);
        $onlinePayments = DB::table('gym_online_payments')
            ->where('target_type', 'membership_order')
            ->where('target_id', $order->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $order->setRelation('onlinePayments', $onlinePayments);

        return view('staff.orders.show', compact('order'));
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

        return redirect()->route('staff.orders.show', $order)->with('success', 'Đã ghi nhận khoản tiền. Trạng thái đơn mới nhất đã được tải lại.');
    }

    public function confirm(Request $request, MembershipOrder $order, MembershipOrderService $service): RedirectResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ]);

        $result = $service->confirmForStaff(
            $order,
            $request->user(),
            $validated['start_date'],
            isset($validated['duration_months']) ? (int) $validated['duration_months'] : null,
        );

        $message = $result['already_confirmed']
            ? 'Đơn này đã được xử lý trước đó. Trang đã tải lại trạng thái mới nhất; lần xử lý này không tạo thêm gói tập.'
            : 'Đã xác nhận thanh toán và kích hoạt gói tập.';

        return redirect()->route('staff.orders.show', $order)->with('success', $message);
    }

    public function cancel(MembershipOrder $order, MembershipOrderService $service): RedirectResponse
    {
        $cancelled = $service->cancel($order);
        $message = $cancelled ? 'Đơn đã được hủy.' : 'Đơn đã được hủy trước đó. Trang đã tải lại trạng thái mới nhất.';

        return redirect()->route('staff.orders.show', $order)->with('success', $message);
    }
}
