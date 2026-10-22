@extends('layouts.app')

@section('title', $pageTitle ?? 'Gói tập')

@section('content')
    <section class="border-y-2 border-[#5eb1ec] bg-[#c5eff7] py-12 sm:py-16">
        <div class="gym-container text-center">
            <p class="text-sm font-bold uppercase tracking-[0.22em] text-gym-blue-deep">The Gym · Mọi người đều được chào đón</p>
            <h1 class="gym-title mt-2">{{ $pageTitle ?? 'Gói tập' }}</h1>
            <p class="mx-auto mt-3 max-w-3xl text-slate-700">Chọn gói tập phù hợp và tập luyện không giới hạn trong không gian <strong>không phán xét</strong>.</p>
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
                            <x-package-cart-form :package="$package" :last-training-day="$lastTrainingDays[$package->id]" />
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

@endsection
