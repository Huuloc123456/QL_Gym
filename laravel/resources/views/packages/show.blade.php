@extends('layouts.app')

@section('title', $package->name)

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <div class="mx-auto grid max-w-5xl gap-8 lg:grid-cols-[1fr_0.85fr] lg:items-start">
            <article class="gym-card p-6 sm:p-8">
                <a class="text-sm font-semibold text-gym-blue underline underline-offset-4" href="{{ route('packages.index') }}">← Tất cả gói tập</a>
                <p class="mt-8 text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">The Gym · Gói hội viên</p>
                <h1 class="gym-title mt-2">{{ $package->name }}</h1>
                <p class="mt-5 text-3xl font-extrabold tabular-nums">{{ number_format($package->price, 0, ',', '.') }} VNĐ</p>
                <p class="mt-1 text-slate-600">Thời hạn {{ $package->duration_months }} tháng</p>
                <p class="mt-6 border-y border-slate-100 py-5 leading-7 text-slate-700">{{ $package->description ?: 'Tập luyện không giới hạn trong thời hạn gói.' }}</p>
                <p class="mt-5 rounded-xl bg-sky-50 p-4 text-sm leading-6 text-sky-950">Ngày tập cuối dự kiến nếu bắt đầu {{ $suggestedStartDate->format('d/m/Y') }}: <strong>{{ $lastTrainingDay->format('d/m/Y') }}</strong>. Gói có hiệu lực đến hết ngày này.</p>
            </article>

            <aside class="gym-card p-6 sm:p-8">
                <h2 class="text-xl font-bold">Đăng ký gói tập</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">Sau khi gửi đăng ký, nhân viên sẽ xác nhận khi nhận đủ thanh toán.</p>
                @auth
                    @if (auth()->user()->role === 'customer')
                        <form class="mt-6 space-y-4" method="POST" action="{{ route('orders.store') }}">
                            @csrf
                            <input type="hidden" name="gym_package_id" value="{{ $package->id }}">
                            <div>
                                <label class="gym-label" for="start-date">Ngày bắt đầu</label>
                                <input class="gym-input" id="start-date" type="date" name="start_date" min="{{ now(config('app.timezone'))->toDateString() }}" value="{{ old('start_date', $suggestedStartDate->toDateString()) }}" required>
                            </div>
                            <div>
                                <label class="gym-label" for="payment-method">Dự kiến thanh toán</label>
                                <select class="gym-input" id="payment-method" name="payment_method" required>
                                    <option value="bank_transfer" @selected(old('payment_method', 'bank_transfer') === 'bank_transfer')>Chuyển khoản</option>
                                    <option value="cash" @selected(old('payment_method') === 'cash')>Tiền mặt tại quầy</option>
                                </select>
                            </div>
                            <button class="gym-btn-primary w-full" type="submit">Gửi đăng ký</button>
                        </form>
                    @else
                        <p class="mt-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">Đăng nhập bằng tài khoản hội viên để đăng ký gói.</p>
                    @endif
                @else
                    <a class="gym-btn-primary mt-6 w-full" href="{{ route('login', ['package_id' => $package->id]) }}">Đăng nhập để đăng ký</a>
                @endauth
            </aside>
        </div>
    </section>
@endsection
