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
                    <x-input name="name" label="Họ và tên" autocomplete="name" required />
                    <x-input name="username" label="Tên đăng nhập" autocomplete="username" required />
                    <x-input name="phone" label="Số điện thoại" type="tel" autocomplete="tel" required />
                    <x-input name="email" label="Email" type="email" autocomplete="email" required />
                    <x-input name="birth_date" label="Ngày sinh (để xét hạng khách)" type="date" autocomplete="bday" />
                    <label class="grid gap-2 text-sm font-semibold text-slate-700">Giới tính
                        <select name="gender" class="gym-input"><option value="">Chưa cung cấp</option><option value="male" @selected(old('gender') === 'male')>Nam</option><option value="female" @selected(old('gender') === 'female')>Nữ</option><option value="other" @selected(old('gender') === 'other')>Khác</option></select>
                    </label>
                    <x-input name="password" label="Mật khẩu" type="password" autocomplete="new-password" required />
                    <x-input name="password_confirmation" label="Nhập lại mật khẩu" type="password" autocomplete="new-password" required />
                    <button class="gym-btn-primary w-full sm:col-span-2" type="submit">Tạo tài khoản</button>
                </form>
                <p class="mt-5 text-center text-sm text-slate-600">Đã có tài khoản? <a class="font-semibold text-gym-blue underline underline-offset-2" href="{{ route('login') }}">Đăng nhập</a></p>
            </div>
        </div>
    </section>
@endsection

