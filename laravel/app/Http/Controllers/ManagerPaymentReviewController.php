<?php

namespace App\Http\Controllers;

use App\Services\VnPayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManagerPaymentReviewController extends Controller
{
    public function index(VnPayService $payments): View
    {
        return view('manager.payment-reviews.index', ['payments' => $payments->reviewQueue()]);
    }

    public function confirm(Request $request, int $payment, VnPayService $payments): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['required', 'string', 'min:8', 'max:1000'],
        ]);
        $payments->resolveReview($payment, $request->user(), 'confirmed', $data);

        return to_route('manager.payment-reviews.index')->with('success', 'Đã xác nhận giao dịch và cập nhật đơn hàng.');
    }

    public function refund(Request $request, int $payment, VnPayService $payments): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'min:3', 'max:100'],
            'note' => ['required', 'string', 'min:8', 'max:1000'],
        ]);
        $payments->resolveReview($payment, $request->user(), 'refunded', $data);

        return to_route('manager.payment-reviews.index')->with('success', 'Đã lưu việc hoàn tiền đã thực hiện bên ngoài cổng thanh toán.');
    }
}
