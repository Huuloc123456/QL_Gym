<?php

namespace App\Http\Controllers;

use App\Services\GymRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManagerRefundController extends Controller
{
    public function store(Request $request, GymRefundService $refunds): RedirectResponse
    {
        $data = $request->validate([
            'source_type' => ['required', 'in:receipt,order_payment'],
            'source_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000000000'],
            'reference' => ['required', 'string', 'min:3', 'max:100'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'token' => ['required', 'uuid'],
        ]);
        $refunds->record($request->user(), $data);

        return back()->with('success', 'Đã lưu chứng từ hoàn tiền đã thực hiện.');
    }
}
