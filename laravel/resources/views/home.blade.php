@extends('layouts.app')

@section('title', 'Hội viên')

@section('content')
    <section class="border-y-2 border-[#5eb1ec] bg-[#c5eff7] py-12 sm:py-16">
        <div class="gym-container text-center">
            <p class="text-sm font-bold uppercase tracking-[0.22em] text-gym-blue-deep">The Gym · Mọi người đều được chào đón</p>
            <h1 class="gym-title mt-2 uppercase">Hội viên The Gym</h1>
            <p class="mx-auto mt-3 max-w-3xl text-slate-700">Chọn gói tập phù hợp và tập luyện không giới hạn trong không gian <strong>không phán xét</strong>.</p>
            <a class="mt-5 inline-flex font-semibold text-gym-blue-deep underline decoration-2 underline-offset-4 hover:text-gym-ink" href="{{ route('packages.index') }}">Xem các gói tập</a>
        </div>

        <div class="gym-package-grid">
            @forelse ($packages as $package)
                @php($isAnnual = $package->duration_months >= 12)
                <article class="gym-package-card {{ $isAnnual ? 'gym-package-card--annual' : '' }}">
                    <div class="flex flex-col items-start gap-3">
                        <h2 class="text-xl font-extrabold uppercase"><a class="rounded-sm hover:text-gym-blue focus-visible:outline-2 focus-visible:outline-gym-blue" href="{{ route('packages.show', $package) }}">{{ $package->name }}</a></h2>
                        <span class="shrink-0 rounded-md bg-gym-mint px-2 py-1 text-xs font-extrabold uppercase text-slate-900">The Gym</span>
                    </div>
                    <p class="mt-5 text-2xl font-extrabold tabular-nums">{{ number_format($package->price, 0, ',', '.') }} VNĐ <span class="text-sm font-medium">/ {{ $package->duration_months }} tháng</span></p>
                    <p class="mt-4 min-h-24 border-y border-current/20 py-4 text-sm leading-6">{{ $package->description ?: 'Tập luyện không giới hạn trong thời hạn gói.' }}</p>
                    @auth
                        @if (auth()->user()->role === 'customer')
                            @if ($package->sql_package_id)
                                <x-package-cart-form :package="$package" :last-training-day="$lastTrainingDays[$package->id]" />
                            @else
                            <form class="mt-5 space-y-3" method="POST" action="{{ route('orders.store') }}" data-membership-purchase data-duration-months="{{ $package->duration_months }}">
                                @csrf
                                <p class="text-sm font-semibold" data-last-training-day>Ngày tập cuối dự kiến: {{ $lastTrainingDays[$package->id] }}</p>
                                <input type="hidden" name="gym_package_id" value="{{ $package->id }}">
                                <div><label class="gym-label {{ $isAnnual ? 'text-white' : '' }}" for="start-date-{{ $package->id }}">Ngày bắt đầu</label><x-form-input id="start-date-{{ $package->id }}" type="date" name="start_date" data-start-date autocomplete="off" min="{{ now(config('app.timezone'))->toDateString() }}" value="{{ old('start_date', $suggestedStartDate->toDateString()) }}" required /></div>
                                <div><label class="gym-label {{ $isAnnual ? 'text-white' : '' }}" for="payment-{{ $package->id }}">Dự kiến thanh toán</label><x-form-select id="payment-{{ $package->id }}" name="payment_method" required><option value="bank_transfer">Chuyển khoản</option><option value="cash">Tiền mặt tại quầy</option></x-form-select></div>
                                <button class="gym-btn-primary w-full" type="submit">Đăng ký gói</button>
                            </form>
                            @endif
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

    <section class="gym-container py-12 sm:py-16"><div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-sm font-semibold uppercase tracking-widest text-slate-500">Bổ sung cho buổi tập</p><h2 class="mt-2 text-2xl font-bold">Sản phẩm tại The Gym</h2></div><a class="text-sm font-semibold text-gym-blue underline" href="{{ route('gym.products.index') }}">Xem tất cả sản phẩm →</a></div><div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">@foreach ($featuredProducts as $product)<x-product-card :product="$product" />@endforeach</div></section>

    <section class="gym-container grid gap-5 py-12 sm:grid-cols-2 sm:py-16" aria-label="Khám phá The Gym">
        <a class="group relative isolate flex min-h-80 items-center justify-center overflow-hidden rounded-2xl p-8 text-center text-white" href="#trial">
            <img class="absolute inset-0 -z-20 h-full w-full object-cover transition-transform group-hover:scale-105" src="{{ asset('images/csharp/promotion3.jpg') }}" alt="Không gian The Gym" width="600" height="800" loading="lazy">
            <span class="absolute inset-0 -z-10 bg-slate-950/50" aria-hidden="true"></span>
            <span><span class="block text-3xl font-bold uppercase">Khám phá The Gym</span><span class="mt-3 block text-sm">Đăng ký trải nghiệm phòng tập →</span></span>
        </a>
        <a class="group relative isolate flex min-h-80 items-center justify-center overflow-hidden rounded-2xl p-8 text-center text-white" href="{{ route('packages.index') }}">
            <img class="absolute inset-0 -z-20 h-full w-full object-cover transition-transform group-hover:scale-105" src="{{ asset('images/csharp/promotion4.jpg') }}" alt="Tập luyện cùng The Gym" width="600" height="800" loading="lazy">
            <span class="absolute inset-0 -z-10 bg-slate-950/50" aria-hidden="true"></span>
            <span><span class="block text-3xl font-bold uppercase">Trở thành hội viên</span><span class="mt-3 block text-sm">Tìm gói tập dành cho bạn →</span></span>
        </a>
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
                <div><label class="gym-label text-white" for="trial-name">Họ và tên</label><x-form-input id="trial-name" type="text" name="name" autocomplete="name" value="{{ old('name') }}" required /></div>
                <div><label class="gym-label text-white" for="trial-phone">Số điện thoại</label><x-form-input id="trial-phone" type="tel" name="phone" autocomplete="tel" value="{{ old('phone') }}" required /></div>
                <div><label class="gym-label text-white" for="trial-email">Email (không bắt buộc)</label><x-form-input id="trial-email" type="email" name="email" autocomplete="email" value="{{ old('email') }}" /></div>
                <button class="gym-btn-primary bg-slate-900 hover:bg-slate-800" type="submit">Đăng ký trải nghiệm</button>
            </form>
        </div>
    </section>
@endsection
