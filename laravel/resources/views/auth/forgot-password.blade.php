@extends('layouts.app')

@section('title', 'Khôi phục mật khẩu')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <div class="mx-auto max-w-lg">
            <div class="gym-card p-6 sm:p-9">
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Lấy lại quyền truy cập</p>
                <h1 class="gym-title mt-2">Khôi phục mật khẩu</h1>
                @if (in_array(config('mail.default'), ['log', 'array'], true))
                    <p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm leading-6 text-amber-900">Email đang ở chế độ kiểm tra, chưa gửi thư thật. Nếu cần lấy lại tài khoản, vui lòng <a class="font-semibold underline" href="{{ route('gym.contact') }}">liên hệ quầy</a>. Liên kết tạo bên dưới chỉ được lưu trong môi trường local.</p>
                @endif
                <p class="mt-2 text-slate-600">Nhập email đã đăng ký. Nếu email thuộc một tài khoản, The Gym sẽ gửi liên kết đổi mật khẩu.</p>
                <form class="mt-6 space-y-4" method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <x-input name="email" label="Email tài khoản" type="email" autocomplete="email" required autofocus />
                    <button class="gym-btn-primary w-full" type="submit">Gửi liên kết khôi phục</button>
                </form>
                <p class="mt-5 text-center text-sm text-slate-600"><a class="font-semibold text-gym-blue underline underline-offset-2" href="{{ route('login') }}">Quay lại đăng nhập</a></p>
            </div>
        </div>
    </section>
@endsection
