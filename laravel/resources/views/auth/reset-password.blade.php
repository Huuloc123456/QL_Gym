@extends('layouts.app')

@section('title', 'Đặt mật khẩu mới')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <div class="mx-auto max-w-lg">
            <div class="gym-card p-6 sm:p-9">
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Một bước cuối</p>
                <h1 class="gym-title mt-2">Đặt mật khẩu mới</h1>
                <p class="mt-2 text-slate-600">Liên kết chỉ dùng một lần và hết hạn sau 60 phút.</p>
                <form class="mt-6 space-y-4" method="POST" action="{{ route('password.update') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div>
                        <label class="gym-label" for="email">Email tài khoản</label>
                        <input class="gym-input" id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required readonly>
                    </div>
                    <div>
                        <label class="gym-label" for="password">Mật khẩu mới</label>
                        <input class="gym-input" id="password" name="password" type="password" autocomplete="new-password" minlength="8" required autofocus>
                    </div>
                    <div>
                        <label class="gym-label" for="password_confirmation">Nhập lại mật khẩu mới</label>
                        <input class="gym-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                    </div>
                    <button class="gym-btn-primary w-full" type="submit">Lưu mật khẩu mới</button>
                </form>
                <p class="mt-5 text-center text-sm text-slate-600"><a class="font-semibold text-gym-blue underline underline-offset-2" href="{{ route('login') }}">Quay lại đăng nhập</a></p>
            </div>
        </div>
    </section>
@endsection
