@extends('layouts.app')

@section('title', 'Đổi mật khẩu')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <div class="gym-card mx-auto max-w-2xl p-6 sm:p-8">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Bảo mật tài khoản</p>
            <h1 class="gym-title mt-2">Đổi mật khẩu</h1>
            <p class="mt-2 text-slate-600">Nhập mật khẩu hiện tại để xác nhận, sau đó chọn mật khẩu mới có ít nhất 8 ký tự.</p>
            <form class="mt-6 space-y-5" method="POST" action="{{ route('account.password.update') }}">
                @csrf
                @method('PUT')
                <div><label class="gym-label" for="current_password">Mật khẩu hiện tại</label><x-form-input id="current_password" type="password" name="current_password" autocomplete="current-password" required /></div>
                <div><label class="gym-label" for="password">Mật khẩu mới</label><x-form-input id="password" type="password" name="password" autocomplete="new-password" minlength="8" required /></div>
                <div><label class="gym-label" for="password_confirmation">Nhập lại mật khẩu mới</label><x-form-input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required /></div>
                <div class="flex flex-wrap justify-between gap-3 border-t border-slate-100 pt-5">
                    <a class="gym-btn-outline" href="{{ url()->previous() }}">Quay lại</a>
                    <button class="gym-btn-primary" type="submit">Lưu mật khẩu mới</button>
                </div>
            </form>
        </div>
    </section>
@endsection
