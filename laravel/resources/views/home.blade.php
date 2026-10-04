@extends('layouts.app')

@section('title', 'Hội viên')

@section('content')
    <section class="border-y-2 border-sky-300 bg-[#c5eff7] py-12 sm:py-16">
        <div class="gym-container text-center">
            <p class="text-sm font-bold uppercase tracking-[0.22em] text-gym-blue-deep">The Gym · Mọi người đều được chào đón</p>
            <h1 class="gym-title mt-2">Hội viên The Gym</h1>
            <p class="mx-auto mt-3 max-w-3xl text-slate-700">Chọn gói tập phù hợp và tập luyện không giới hạn trong không gian <strong>không phán xét</strong>.</p>
            <a class="mt-5 inline-flex font-semibold text-gym-blue-deep underline decoration-2 underline-offset-4 hover:text-gym-ink" href="{{ route('packages.index') }}">Xem các gói tập</a>
        </div>

        <div class="gym-container mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($packages as $package)
                @php($isAnnual = $package->duration_months >= 12)
                <article class="flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_16px_44px_rgba(20,48,68,0.12)] {{ $isAnnual ? 'bg-slate-900 text-white' : '' }}">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="text-xl font-extrabold uppercase"><a class="rounded-sm hover:text-gym-blue focus-visible:outline-2 focus-visible:outline-gym-blue" href="{{ route('packages.show', $package) }}">{{ $package->name }}</a></h2>
                        <span class="shrink-0 rounded-md bg-gym-mint px-2 py-1 text-xs font-extrabold uppercase text-slate-900">The Gym</span>
                    </div>
                    <p class="mt-5 text-2xl font-extrabold tabular-nums">{{ number_format($package->price, 0, ',', '.') }} VNĐ <span class="text-sm font-medium">/ {{ $package->duration_months }} tháng</span></p>
                    <p class="mt-4 min-h-24 border-y border-current/20 py-4 text-sm leading-6">{{ $package->description ?: 'Tập luyện không giới hạn trong thời hạn gói.' }}</p>
                    @auth
                        @if (auth()->user()->role === 'customer')
                            <form class="mt-5 space-y-3" method="POST" action="{{ route('orders.store') }}" data-membership-purchase data-duration-months="{{ $package->duration_months }}">
                                @csrf
                                <p class="text-sm font-semibold" data-last-training-day>Ngày tập cuối dự kiến: {{ $lastTrainingDays[$package->id] }}</p>
                                <input type="hidden" name="gym_package_id" value="{{ $package->id }}">
                                <div><label class="gym-label {{ $isAnnual ? 'text-white' : '' }}" for="start-date-{{ $package->id }}">Ngày bắt đầu</label><input class="gym-input" id="start-date-{{ $package->id }}" type="date" name="start_date" data-start-date autocomplete="off" min="{{ now(config('app.timezone'))->toDateString() }}" value="{{ old('start_date', $suggestedStartDate->toDateString()) }}" required></div>
                                <div><label class="gym-label {{ $isAnnual ? 'text-white' : '' }}" for="payment-{{ $package->id }}">Dự kiến thanh toán</label><select class="gym-input" id="payment-{{ $package->id }}" name="payment_method" required><option value="bank_transfer">Chuyển khoản</option><option value="cash">Tiền mặt tại quầy</option></select></div>
                                <button class="gym-btn-primary w-full" type="submit">Đăng ký gói</button>
                            </form>
                        @else
                            <p class="mt-4 text-sm">Ngày tập cuối dự kiến nếu bắt đầu hôm nay: {{ $lastTrainingDays[$package->id] }}</p>
                            <a class="gym-btn-primary mt-4 w-full" href="{{ route('login', ['package_id' => $package->id]) }}">Tham gia</a>
                        @endif
                    @endauth
                    @guest
                        <p class="mt-4 text-sm">Ngày tập cuối dự kiến nếu bắt đầu hôm nay: {{ $lastTrainingDays[$package->id] }}</p>
                        <a class="gym-btn-primary mt-4 w-full" href="{{ route('login', ['package_id' => $package->id]) }}">Tham gia</a>
                    @endguest
                </article>
            @empty
                <p class="sm:col-span-2 xl:col-span-3">Hiện chưa có gói tập mở bán.</p>
            @endforelse
        </div>
    </section>

    <section id="trial" class="scroll-mt-24 bg-gym-blue py-12 text-white sm:py-16">
        <div class="gym-container grid gap-8 md:grid-cols-2 md:items-center">
            <div class="max-w-xl">
                <p class="text-sm font-bold uppercase tracking-[0.2em] text-sky-100">Bắt đầu từ buổi đầu tiên</p>
                <h2 class="mt-2 text-3xl font-extrabold uppercase tracking-tight sm:text-4xl">Trải nghiệm miễn phí ngay!</h2>
                <p class="mt-3 leading-7 text-sky-50">Để lại thông tin, đội ngũ The Gym sẽ liên hệ và hỗ trợ bạn trải nghiệm phòng tập.</p>
            </div>
            <form class="space-y-4" method="POST" action="{{ route('trials.store') }}">
                @csrf
                <div><label class="gym-label text-white" for="trial-name">Họ và tên</label><input class="gym-input" id="trial-name" type="text" name="name" autocomplete="name" value="{{ old('name') }}" required></div>
                <div><label class="gym-label text-white" for="trial-phone">Số điện thoại</label><input class="gym-input" id="trial-phone" type="tel" name="phone" autocomplete="tel" value="{{ old('phone') }}" required></div>
                <div><label class="gym-label text-white" for="trial-email">Email (không bắt buộc)</label><input class="gym-input" id="trial-email" type="email" name="email" autocomplete="email" value="{{ old('email') }}"></div>
                <button class="gym-btn-primary bg-slate-900 hover:bg-slate-800" type="submit">Đăng ký trải nghiệm</button>
            </form>
        </div>
    </section>
@endsection
