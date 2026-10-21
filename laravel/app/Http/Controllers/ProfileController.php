<?php

namespace App\Http\Controllers;

use App\Services\OriginalGymSql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private OriginalGymSql $sql) {}

    public function show(Request $request): View
    {
        return view('profile.show', ['customer' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone,'.$request->user()->id, Rule::unique('KHACHHANG', 'SDT')->ignore($request->user()->sql_customer_id, 'MAKH')],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:male,female,other'],
            'username' => ['prohibited'],
            'email' => ['prohibited'],
        ]);

        $this->sql->transaction(function () use ($request, $validated): void {
            $request->user()->update(collect($validated)->only(['name', 'phone', 'birth_date', 'gender'])->all());
        });

        return redirect()->route('my.profile.show')->with('success', 'Thông tin cá nhân đã được cập nhật.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)]]);
        $this->sql->transaction(function () use ($request, $data): void {
            $request->user()->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();
            $this->sql->audit('Đổi mật khẩu', 'users:'.$request->user()->id);
        });
        $request->session()->regenerate();

        return back()->with('success', 'Đã đổi mật khẩu và kết thúc các phiên đăng nhập khác.');
    }
}
