<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GymIdentityService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private GymIdentityService $identities) {}

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
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
            'package_id' => ['nullable', 'integer', 'exists:gym_packages,id'],
        ]);

        $ready = Schema::hasTable('KHACHHANG');
        if ($ready) {
            $this->identities->importLogin($data['username'], $data['password']);
        }

        $credentials = [
            'username' => $data['username'],
            'password' => $data['password'],
            'is_active' => true,
        ];

        if (! Auth::validate($credentials)) {
            if ($ready) {
                $this->identities->loginHistory($data['username'], false, $request->ip());
            }

            return back()->withErrors(['username' => 'Tên đăng nhập hoặc mật khẩu chưa chính xác.'])->onlyInput('username');
        }

        $user = User::query()->where('username', $data['username'])->firstOrFail();
        if ($ready) {
            $this->identities->sync($user);
        }

        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['username' => 'Tài khoản không còn khả dụng.'])->onlyInput('username');
        }

        if ($ready) {
            $this->identities->loginHistory($data['username'], true, $request->ip());
        }

        $request->session()->regenerate();

        if ($request->user()->role === 'manager') {
            return redirect()->route('manager.dashboard');
        }
        if ($request->user()->role === 'employee') {
            return redirect()->route('staff.dashboard');
        }
        if (isset($data['package_id'])) {
            return redirect()->route('packages.show', ['package' => $data['package_id']]);
        }

        return redirect()->intended(route('my.memberships.index'));
    }

    public function register(Request $request): RedirectResponse
    {
        $ready = Schema::hasTable('KHACHHANG');
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'alpha_dash', 'max:50', 'unique:users,username'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'package_id' => ['nullable', 'integer', 'exists:gym_packages,id'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
        ];

        if ($ready) {
            $rules['username'][] = 'unique:KHACHHANG,TENDANGNHAP';
            $rules['phone'][] = 'unique:KHACHHANG,SDT';
            $rules['email'][] = 'unique:KHACHHANG,EMAIL';
        }

        $validated = $request->validate($rules);

        try {
            $customer = DB::transaction(fn (): User => User::query()->create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'role' => 'customer',
                'password' => $validated['password'],
                'birth_date' => $validated['birth_date'] ?? null,
                'gender' => $validated['gender'] ?? null,
            ]));
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '45000'], true)) {
                return back()->withErrors(['username' => 'Tên đăng nhập, số điện thoại hoặc email đã được đăng ký.'])
                    ->onlyInput('name', 'username', 'phone', 'email');
            }

            throw $exception;
        }

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
