@extends('layouts.app')

@section('title', 'Đăng nhập')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <div class="mx-auto max-w-lg">
            <div class="gym-card p-6 sm:p-9">
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Chào mừng bạn trở lại</p>
                <h1 class="gym-title mt-2">Đăng nhập</h1>
                <p class="mt-2 text-slate-600">Truy cập thông tin hội viên The Gym.</p>
                <form class="mt-6 space-y-4" method="POST" action="{{ route('login.store') }}">
                    @csrf
                    @if ($packageId > 0)<input type="hidden" name="package_id" value="{{ $packageId }}">@endif
                    <div><label class="gym-label" for="username">Tên đăng nhập</label><input class="gym-input" id="username" name="username" value="{{ old('username') }}" autocomplete="username" required autofocus></div>
                    <div><label class="gym-label" for="password">Mật khẩu</label><input class="gym-input" id="password" name="password" type="password" autocomplete="current-password" required></div>
                    <p class="-mt-2 text-right text-sm"><a class="font-semibold text-gym-blue underline underline-offset-2" href="{{ route('password.request') }}">Quên mật khẩu?</a></p>
                    <button class="gym-btn-primary w-full" type="submit">Đăng nhập</button>
                </form>
                <p class="mt-5 text-center text-sm text-slate-600">Chưa là hội viên? <a class="font-semibold text-gym-blue underline underline-offset-2" href="{{ route('register', $packageId > 0 ? ['package_id' => $packageId] : []) }}">Tạo tài khoản</a></p>
            </div>
        </div>
    </section>
@endsection

