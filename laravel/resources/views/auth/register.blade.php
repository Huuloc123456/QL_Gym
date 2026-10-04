@extends('layouts.app')

@section('title', 'Tạo tài khoản')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <div class="mx-auto max-w-2xl">
            <div class="gym-card p-6 sm:p-9">
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Bắt đầu tập luyện</p>
                <h1 class="gym-title mt-2">Tạo tài khoản hội viên</h1>
                <p class="mt-2 text-slate-600">Dùng tên đăng nhập và mật khẩu để quản lý gói tập.</p>
                <form class="mt-6 grid gap-4 sm:grid-cols-2" method="POST" action="{{ route('register.store') }}">
                    @csrf
                    @if ($packageId > 0)<input type="hidden" name="package_id" value="{{ $packageId }}">@endif
                    <div><label class="gym-label" for="name">Họ và tên</label><input class="gym-input" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required></div>
                    <div><label class="gym-label" for="register-username">Tên đăng nhập</label><input class="gym-input" id="register-username" name="username" value="{{ old('username') }}" autocomplete="username" required></div>
                    <div><label class="gym-label" for="phone">Số điện thoại</label><input class="gym-input" id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" required></div>
                    <div><label class="gym-label" for="email">Email</label><input class="gym-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required></div>
                    <div><label class="gym-label" for="register-password">Mật khẩu</label><input class="gym-input" id="register-password" name="password" type="password" autocomplete="new-password" required></div>
                    <div><label class="gym-label" for="password_confirmation">Nhập lại mật khẩu</label><input class="gym-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
                    <button class="gym-btn-primary w-full sm:col-span-2" type="submit">Tạo tài khoản</button>
                </form>
                <p class="mt-5 text-center text-sm text-slate-600">Đã có tài khoản? <a class="font-semibold text-gym-blue underline underline-offset-2" href="{{ route('login') }}">Đăng nhập</a></p>
            </div>
        </div>
    </section>
@endsection

