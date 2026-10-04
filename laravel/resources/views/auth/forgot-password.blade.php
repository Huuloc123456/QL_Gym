@extends('layouts.app')

@section('title', 'Khôi phục mật khẩu')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <div class="mx-auto max-w-lg">
            <div class="gym-card p-6 sm:p-9">
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Lấy lại quyền truy cập</p>
                <h1 class="gym-title mt-2">Khôi phục mật khẩu</h1>
                <p class="mt-2 text-slate-600">Nhập email đã đăng ký. Nếu email thuộc một tài khoản, The Gym sẽ gửi liên kết đổi mật khẩu.</p>
                <form class="mt-6 space-y-4" method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <div>
                        <label class="gym-label" for="email">Email tài khoản</label>
                        <input class="gym-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                    </div>
                    <button class="gym-btn-primary w-full" type="submit">Gửi liên kết khôi phục</button>
                </form>
                <p class="mt-5 text-center text-sm text-slate-600"><a class="font-semibold text-gym-blue underline underline-offset-2" href="{{ route('login') }}">Quay lại đăng nhập</a></p>
            </div>
        </div>
    </section>
@endsection
