@extends('layouts.app')
@section('title', 'Huấn luyện viên cá nhân')
@section('content')
<section class="gym-container py-10">
    <h1 class="gym-title">Tập luyện cùng PT</h1>
    <p class="mt-3 text-slate-500">Chọn huấn luyện viên, đăng ký số buổi và xếp lịch sau khi xác nhận thanh toán.</p>
    <div class="mt-7 grid items-start gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($trainers as $trainer)
            <article class="gym-card">
                <span class="grid size-16 place-items-center rounded-full bg-sky-50 text-xl font-bold text-gym-blue" aria-hidden="true">PT</span>
                <h2 class="mt-4 text-xl font-bold">{{ $trainer->TENNV }}</h2>
                <p class="mt-3 text-sm text-slate-600">{{ ($specialties[$trainer->MANV] ?? collect())->pluck('TENCHUYENMON')->join(', ') ?: 'Chuyên môn đang cập nhật' }}</p>
                @if ($trainer->price && isset($specialties[$trainer->MANV]))
                    <p class="mt-4 font-bold text-gym-blue">{{ number_format($trainer->price, 0, ',', '.') }}đ / buổi</p>
                    @if (auth()->user()?->role === 'customer')
                        <form class="mt-5 space-y-4" method="POST" action="{{ route('gym.pt.order', $trainer->MANV) }}">
                            @csrf
                            <input type="hidden" name="token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <div><label class="gym-label" for="sessions-{{ $trainer->MANV }}">Số buổi</label><input class="gym-input" id="sessions-{{ $trainer->MANV }}" type="number" name="sessions" min="1" max="100" value="1" required></div>
                            <div><label class="gym-label" for="method-{{ $trainer->MANV }}">Thanh toán</label><select class="gym-input" id="method-{{ $trainer->MANV }}" name="payment_method">@if (config('vnpay.tmn_code') && config('vnpay.hash_secret'))<option value="vnpay">Thanh toán online qua VNPay</option>@endif<option value="cash">Tiền mặt tại quầy</option><option value="bank_transfer">Chuyển khoản, nhân viên đối chiếu</option></select></div>
                            <button class="gym-btn-primary w-full" type="submit">Đăng ký PT</button>
                        </form>
                    @else
                        <a class="gym-btn-primary mt-5" href="{{ route('login') }}">Đăng nhập để đăng ký</a>
                    @endif
                @else
                    <p class="mt-5 text-sm text-slate-500">Chưa mở đăng ký trực tuyến. Vui lòng liên hệ quầy.</p>
                @endif
            </article>
        @empty
            <p>Chưa có PT được phân công.</p>
        @endforelse
    </div>
</section>
@endsection
