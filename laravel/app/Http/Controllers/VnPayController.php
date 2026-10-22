<?php

namespace App\Http\Controllers;

use App\Models\MembershipOrder;
use App\Services\VnPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VnPayController extends Controller
{
    public function membershipOrder(Request $request, MembershipOrder $order, VnPayService $payments): RedirectResponse
    {
        return redirect()->away($payments->startMembershipOrder($order, $request->user()));
    }

    public function invoice(Request $request, int $invoice, VnPayService $payments): RedirectResponse
    {
        return redirect()->away($payments->startInvoice($invoice, $request->user()));
    }

    public function ipn(Request $request, VnPayService $payments): JsonResponse
    {
        return response()->json($payments->handleIpn($request->query()));
    }

    public function returned(Request $request, VnPayService $payments): View
    {
        $data = $request->validate(['vnp_TxnRef' => ['required', 'string', 'max:64']]);
        $payment = $payments->paymentForReturn($data['vnp_TxnRef']);

        return view('gym.vnpay-result', ['payment' => $payment]);
    }
}
