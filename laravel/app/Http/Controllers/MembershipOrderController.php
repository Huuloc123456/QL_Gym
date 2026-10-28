<?php

namespace App\Http\Controllers;

use App\Models\GymSetting;
use App\Models\MembershipOrder;
use App\Repositories\GymPackageRepository;
use App\Repositories\MembershipOrderRepository;
use App\Services\GymIdentityService;
use App\Services\MembershipOrderService;
use App\Services\VietQrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MembershipOrderController extends Controller
{
    public function index(Request $request, MembershipOrderRepository $orderRepository, VietQrService $vietQr): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:all,pending,paid,cancelled'],
        ]);
        $filters['status'] ??= 'all';
        $orders = $orderRepository->paginateForCustomer($request->user(), $filters['status']);
        $customer = app(GymIdentityService::class)->customer($request->user());
        $query = DB::table('HOADON as h')->where('h.MAKH', $customer)
            ->where(function ($query): void {
                $query->whereExists(fn ($lines) => $lines->selectRaw('1')->from('gym_invoice_lines as l')->whereColumn('l.invoice_id', 'h.MAHD')->where('l.kind', 'package'))
                    ->orWhereExists(fn ($lines) => $lines->selectRaw('1')->from('CHITIETHOADON as d')->whereColumn('d.MAHD', 'h.MAHD')->whereNotNull('d.MADKGT'));
            });
        $states = ['pending' => 'Chưa thanh toán', 'paid' => 'Đã thanh toán', 'cancelled' => 'Hủy'];
        if (isset($states[$filters['status']])) {
            $query->where('h.TRANGTHAI', $states[$filters['status']]);
        }
        $packageInvoices = $query->select('h.*')->selectSub(DB::table('gym_invoice_lines as l')->selectRaw("GROUP_CONCAT(l.name SEPARATOR ', ')")->whereColumn('l.invoice_id', 'h.MAHD'), 'contents')
            ->orderByDesc('h.MAHD')->paginate(15, ['*'], 'invoice_page')->withQueryString();
        $gymSettings = GymSetting::query()->find(1);
        $qrUrls = $orders->getCollection()->mapWithKeys(function (MembershipOrder $order) use ($gymSettings, $vietQr): array {
            if ($order->status !== 'pending' || $order->payment_method !== 'bank_transfer' || $order->cancellation_requested_at) {
                return [];
            }

            $remaining = max(0, (int) $order->amount - (int) ($order->payments_sum_amount ?? 0));

            return [$order->id => $vietQr->imageUrl($gymSettings, $order->id, $remaining)];
        });

        return view('orders.index', compact('orders', 'filters', 'gymSettings', 'qrUrls', 'packageInvoices'));
    }

    public function show(Request $request, MembershipOrder $order, MembershipOrderRepository $orderRepository, VietQrService $vietQr): View|RedirectResponse
    {
        $order = $orderRepository->findForCustomer($request->user(), $order->id);
        if ($order->sql_invoice_id) {
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
