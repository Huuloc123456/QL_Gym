@extends('layouts.app')

@section('title', 'Gói tập')

@section('content')
    <section class="bg-[#c5eff7] py-12 sm:py-16">
        <div class="gym-container">
            <header class="mx-auto max-w-3xl text-center">
                <p class="text-sm font-bold uppercase tracking-[0.22em] text-gym-blue-deep">The Gym · Chọn gói phù hợp</p>
                <h1 class="gym-title mt-2">Các gói tập</h1>
                <p class="mt-3 text-slate-700">Xem thời hạn và quyền lợi của từng gói trước khi đăng ký.</p>
            </header>

            <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($packages as $package)
                    <article id="package-{{ $package->id }}" class="gym-card flex flex-col p-6 {{ $selectedPackageId === $package->id ? 'ring-2 ring-gym-blue' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="text-xl font-extrabold uppercase">{{ $package->name }}</h2>
                            <span class="shrink-0 rounded-md bg-gym-mint px-2 py-1 text-xs font-extrabold uppercase text-slate-900">The Gym</span>
                        </div>
                        <p class="mt-5 text-2xl font-extrabold tabular-nums">{{ number_format($package->price, 0, ',', '.') }} VNĐ <span class="text-sm font-medium">/ {{ $package->duration_months }} tháng</span></p>
                        <p class="mt-4 flex-1 border-y border-slate-200 py-4 text-sm leading-6 text-slate-600">{{ $package->description ?: 'Tập luyện không giới hạn trong thời hạn gói.' }}</p>
                        <p class="mt-4 text-sm text-slate-600">Ngày tập cuối dự kiến nếu bắt đầu {{ $suggestedStartDate->format('d/m/Y') }}: <strong>{{ $lastTrainingDays[$package->id] }}</strong></p>
                        <a class="gym-btn-primary mt-5 w-full" href="{{ route('packages.show', $package) }}">Xem chi tiết và đăng ký</a>
                    </article>
                @empty
                    <p class="gym-card py-10 text-center text-slate-600 sm:col-span-2 xl:col-span-3">Hiện chưa có gói tập mở bán.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
