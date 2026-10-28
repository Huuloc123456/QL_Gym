<?php

namespace App\Http\Controllers;

use App\Services\GymInvoiceReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManagerInvoiceReconciliationController extends Controller
{
    public function update(Request $request, int $invoice, GymInvoiceReconciliationService $reconciliations): RedirectResponse
    {
        $reconciliations->record($request->user(), $invoice, $request->all());

        return to_route('manager.settings.edit')->with('success', 'Đã lưu kết quả đối soát và ghi nhật ký thao tác.');
    }
}
