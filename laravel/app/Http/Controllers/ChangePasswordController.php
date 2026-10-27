<?php

namespace App\Http\Controllers;

use App\Services\AccountPasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ChangePasswordController extends Controller
{
    public function edit(): View
    {
        return view('account.password');
    }

    public function update(Request $request, AccountPasswordService $passwords): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ]);

        $passwords->change($request->user(), $validated['password'], $request->session()->getId());
        $request->session()->regenerate();

        return redirect()->route('account.password.edit')
            ->with('success', 'Đã đổi mật khẩu và kết thúc các phiên đăng nhập khác.');
    }
}
