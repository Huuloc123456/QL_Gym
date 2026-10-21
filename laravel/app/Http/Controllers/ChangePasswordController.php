<?php

namespace App\Http\Controllers;

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

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $validated['password']]);
        $request->session()->regenerate();

        return redirect()->route('account.password.edit')
            ->with('success', 'Đã đổi mật khẩu.');
    }
}
