@extends('layouts.app')
@section('title', 'Bảo mật tài khoản')
@section('content')
<section class="gym-container py-10"><div class="gym-card mx-auto max-w-xl"><h1 class="gym-title">Đổi mật khẩu</h1><p class="mt-3 text-sm leading-7 text-slate-500">Nhập mật khẩu hiện tại để xác nhận. Sau khi đổi, các phiên đăng nhập khác sẽ kết thúc.</p><form class="mt-6 space-y-4" method="POST" action="{{ route('gym.password') }}">@csrf @method('PUT')<x-input name="current_password" label="Mật khẩu hiện tại" type="password" autocomplete="current-password" required /><x-input name="password" label="Mật khẩu mới (tối thiểu 8 ký tự)" type="password" autocomplete="new-password" required /><x-input name="password_confirmation" label="Nhập lại mật khẩu mới" type="password" autocomplete="new-password" required /><button class="gym-btn-primary w-full" type="submit">Đổi mật khẩu</button></form></div></section>
@endsection
