@extends('layouts.app')
@section('title', 'Xác minh đăng nhập')
@section('content')
<section class="gym-container py-10"><div class="mx-auto max-w-lg gym-card"><h1 class="gym-title">Xác minh đăng nhập</h1><p class="mt-3 text-sm text-slate-500">Nhập mã 6 chữ số đã gửi đến email của bạn. Mã có hiệu lực 5 phút.</p><form class="mt-6 space-y-4" method="POST" action="{{ route('login.otp.verify') }}">@csrf<x-input name="code" label="Mã xác minh" autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus /><button class="gym-btn-primary w-full" type="submit">Xác minh</button></form><a class="mt-5 inline-block text-sm text-gym-blue underline" href="{{ route('login') }}">Đăng nhập lại</a></div></section>
@endsection
