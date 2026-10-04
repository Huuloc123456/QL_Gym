<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request): View
    {
        return view('auth.login', ['packageId' => $request->integer('package_id')]);
    }

    public function showRegistration(Request $request): View
    {
        return view('auth.register', ['packageId' => $request->integer('package_id')]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'package_id' => ['nullable', 'integer', 'exists:gym_packages,id'],
        ]);

        $packageId = $credentials['package_id'] ?? null;
        unset($credentials['package_id']);

        if (! Auth::attempt($credentials)) {
            return back()->withErrors([
                'username' => 'Tên đăng nhập hoặc mật khẩu chưa chính xác.',
            ])->onlyInput('username');
        }

        $request->session()->regenerate();

        if ($request->user()->role === 'manager') {
            return redirect()->route('manager.dashboard');
        }

        if ($request->user()->role === 'employee') {
            return redirect()->route('staff.orders.index');
        }

        if ($packageId !== null && $request->user()->role === 'customer') {
            return redirect()->route('packages.show', ['package' => $packageId]);
        }

        return redirect()->intended(route('my.memberships.index'));
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'max:50', 'unique:users,username'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'package_id' => ['nullable', 'integer', 'exists:gym_packages,id'],
        ]);

        $customer = User::query()->create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'role' => 'customer',
            'password' => $validated['password'],
        ]);

        Auth::login($customer);
        $request->session()->regenerate();

        if (isset($validated['package_id'])) {
            return redirect()->route('packages.show', ['package' => $validated['package_id']]);
        }

        return redirect()->route('my.memberships.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
