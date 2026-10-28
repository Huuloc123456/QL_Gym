<?php

namespace App\Http\Controllers;

use App\Models\GymSetting;
use App\Models\MembershipOrder;
use App\Repositories\GymPackageRepository;
use App\Repositories\MembershipOrderRepository;
use App\Services\MembershipOrderService;
use App\Services\VietQrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MembershipOrderController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $query = $request->query();
        unset($query['page'], $query['invoice_page']);

        $query['status'] = match ($query['status'] ?? 'all') {
            'pending' => 'Chưa thanh toán',
            'paid' => 'Đã thanh toán',
            'cancelled' => 'Hủy',
            default => null,
        };
        $query = array_filter($query, static fn ($value): bool => $value !== null && $value !== '');

        return redirect()->route('gym.invoices.index', $query);
    }

    public function show(Request $request, MembershipOrder $order, MembershipOrderRepository $orderRepository, VietQrService $vietQr): View|RedirectResponse
    {
        $order = $orderRepository->findForCustomer($request->user(), $order->id);
        if ($order->sql_invoice_id && DB::table('gym_fulfillments')->where('invoice_id', $order->sql_invoice_id)->exists()) {
            return redirect()->route('gym.invoices.show', $order->sql_invoice_id);
        }

        $gymSettings = GymSetting::query()->find(1);
        $remainingAmount = max(0, (int) $order->amount - $order->receivedAmount());

        return view('orders.show', [
            'order' => $order,
            'gymSettings' => $gymSettings,
            'bankTransferQrUrl' => $order->status === 'pending' && $order->payment_method === 'bank_transfer' && ! $order->cancellation_requested_at
                ? $vietQr->imageUrl($gymSettings, $order->id, $remainingAmount)
                : null,
            'remainingAmount' => $remainingAmount,
            'onlinePayments' => DB::table('gym_online_payments')->where('target_type', 'membership_order')->where('target_id', $order->id)->where('user_id', $request->user()->id)->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request, GymPackageRepository $packages): RedirectResponse
    {
        $validated = $request->validate([
            'gym_package_id' => ['required', 'integer', 'exists:gym_packages,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'payment_method' => ['required', 'in:cash,bank_transfer,vnpay'],
        ]);

        $package = $packages->findActiveOrFail((int) $validated['gym_package_id']);

        return redirect()->route('packages.show', $package)
            ->with('success', 'Hãy thêm gói vào giỏ và kiểm tra thông tin trước khi đặt hàng.')
            ->withInput();
    }

    public function requestCancellation(Request $request, MembershipOrder $order, MembershipOrderService $orders, MembershipOrderRepository $orderRepository): RedirectResponse
    {
        $orders->requestCancellation($orderRepository->findForCustomer($request->user(), $order->id));

        return redirect()->route('my.orders.show', $order)->with('success', 'Đã gửi yêu cầu hủy. Nhân viên sẽ kiểm tra các khoản đã nhận trước khi xử lý.');
    }
}
