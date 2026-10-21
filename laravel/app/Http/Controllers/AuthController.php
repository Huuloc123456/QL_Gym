<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GymIdentityService;
use App\Services\OriginalGymSql;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private GymIdentityService $identities, private OriginalGymSql $sql) {}

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
        $data = $request->validate(['username' => ['required', 'string', 'max:50'], 'password' => ['required', 'string'], 'package_id' => ['nullable', 'integer', 'exists:gym_packages,id'], 'otp' => ['nullable', 'boolean']]);
        $ready = Schema::hasTable('KHACHHANG');
        if ($ready) {
            $this->identities->importLogin($data['username'], $data['password']);
        }
        $credentials = ['username' => $data['username'], 'password' => $data['password']];
        if ($ready) {
            $credentials['is_active'] = true;
        }
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
        if ($ready && $request->boolean('otp') && $user->role === 'customer') {
            if (! $user->email) {
                return back()->withErrors(['username' => 'Tài khoản chưa có email để nhận mã. Vui lòng đăng nhập thông thường và cập nhật email qua quầy.'])->onlyInput('username');
            }
            $customer = $this->identities->customer($user);
            $code = (string) random_int(100000, 999999);
            $history = $this->sql->transaction(function () use ($customer, $request, $code): int {
                $id = $this->sql->nextId('LOGINHISTORY');
                DB::table('LOGINHISTORY')->insert(['ID' => $id, 'USERID' => $customer, 'IPADDRESS' => $request->ip(), 'LOGINTIME' => now(), 'STATUS' => 0, 'OTPCODE' => $code, 'OTPEXPIRE' => now()->addMinutes(5)]);

                return $id;
            });
            Mail::raw('Mã xác minh đăng nhập The Gym: '.$code.'. Mã có hiệu lực 5 phút. Không chia sẻ mã này.', function ($mail) use ($user): void {
                $mail->to($user->email)->subject('Mã xác minh đăng nhập The Gym');
            });
            $request->session()->regenerate();
            $request->session()->put('gym_login_pending', ['user' => $user->id, 'history' => $history, 'package' => $data['package_id'] ?? null, 'attempts' => 0]);

            return redirect()->route('login.otp');
        }
        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['username' => 'Tài khoản không còn khả dụng.'])->onlyInput('username');
        }
        if ($ready) {
            $this->identities->loginHistory($data['username'], true, $request->ip());
        }

        return $this->finishLogin($request, $data['package_id'] ?? null);
    }

    public function showOtp(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('gym_login_pending')) {
            return redirect()->route('login');
        }

        return view('auth.otp');
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => 'required|digits:6']);
        $pending = $request->session()->get('gym_login_pending');
        if (! $pending) {
            return redirect()->route('login');
        }
        $user = User::query()->where('id', $pending['user'])->where('is_active', true)->first();
        $record = DB::table('LOGINHISTORY')->where('ID', $pending['history'])->first();
        if (! $user || ! $record || ! $record->OTPCODE || ! $record->OTPEXPIRE || now()->gte($record->OTPEXPIRE) || $pending['attempts'] >= 5) {
            $request->session()->forget('gym_login_pending');

            return redirect()->route('login')->withErrors(['username' => 'Mã đã hết hạn hoặc vượt số lần thử. Hãy đăng nhập lại.']);
        }
        if (! hash_equals($record->OTPCODE, $data['code'])) {
            $pending['attempts']++;
            $request->session()->put('gym_login_pending', $pending);

            return back()->withErrors(['code' => 'Mã xác minh chưa đúng.']);
        }
        $this->sql->transaction(function () use ($record, $user, $data): void {
            $fresh = DB::table('LOGINHISTORY')->where('ID', $record->ID)->lockForUpdate()->first();
            if (! $fresh || (int) $fresh->USERID !== $user->sql_customer_id || ! $fresh->OTPCODE || now()->gte($fresh->OTPEXPIRE) || ! hash_equals($fresh->OTPCODE, (string) $data['code'])) {
                throw ValidationException::withMessages(['code' => 'Mã không còn hợp lệ hoặc đã được sử dụng.']);
            }
            $this->sql->call('sp_KhachHangLogin', [$user->username, $user->password]);
            DB::table('LOGINHISTORY')->where('ID', $record->ID)->update(['STATUS' => 1, 'OTPCODE' => null, 'OTPEXPIRE' => null]);
        });
        $request->session()->forget('gym_login_pending');
        Auth::login($user);

        return $this->finishLogin($request, $pending['package']);
    }

    public function register(Request $request): RedirectResponse
    {
        $rules = ['name' => ['required', 'string', 'max:150'], 'username' => ['required', 'string', 'alpha_dash', 'max:50', 'unique:users,username'], 'phone' => ['required', 'string', 'max:20', 'unique:users,phone'], 'email' => ['required', 'email', 'max:100', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::min(8)], 'package_id' => ['nullable', 'integer', 'exists:gym_packages,id']];
        $ready = Schema::hasTable('KHACHHANG');
        if ($ready) {
            $rules['username'][] = 'unique:KHACHHANG,TENDANGNHAP';
            $rules['phone'][] = 'unique:KHACHHANG,SDT';
            $rules['email'][] = 'unique:KHACHHANG,EMAIL';
        }
        $rules['birth_date'] = ['nullable', 'date', 'before:today'];
        $rules['gender'] = ['nullable', 'in:male,female,other'];
        $validated = $request->validate($rules);
        try {
            $customer = DB::transaction(function () use ($validated, $ready): User {
                $user = User::query()->create(['name' => $validated['name'], 'username' => $validated['username'], 'phone' => $validated['phone'], 'email' => $validated['email'], 'role' => 'customer', 'password' => $validated['password'], 'birth_date' => $validated['birth_date'] ?? null, 'gender' => $validated['gender'] ?? null]);
                if ($ready) {
                    $this->identities->customer($user);
                }

                return $user;
            });
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '45000'], true)) {
                return back()->withErrors(['username' => 'Tên đăng nhập, số điện thoại hoặc email đã được đăng ký.'])->onlyInput('name', 'username', 'phone', 'email');
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

    private function finishLogin(Request $request, ?int $packageId): RedirectResponse
    {
        $request->session()->regenerate();
        if ($request->user()->role === 'manager') {
            return redirect()->route('manager.dashboard');
        }
        if ($request->user()->role === 'employee') {
            return redirect()->route('staff.dashboard');
        }
        if ($packageId !== null) {
            return redirect()->route('packages.show', ['package' => $packageId]);
        }

        return redirect()->intended(route('my.memberships.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
