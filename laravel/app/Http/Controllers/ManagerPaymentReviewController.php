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

    public function resolve(Request $request, int $payment, VnPayService $payments): RedirectResponse
    {
        $data = $request->validate([
            'action' => 'required|in:confirmed,refunded',
            'note' => 'required|string|min:8|max:1000',
            'reference' => 'required_if:action,refunded|nullable|string|min:3|max:100',
            'start_date' => 'nullable|date|after_or_equal:today',
            'verified' => 'accepted',
        ]);
        $payments->resolveReview($payment, $request->user(), $data);

        return redirect()->route('manager.payment-reviews.index')->with('success', 'Đã lưu kết quả đối soát. Thao tác này không gửi lệnh hoàn tiền đến ngân hàng.');
    }
}
