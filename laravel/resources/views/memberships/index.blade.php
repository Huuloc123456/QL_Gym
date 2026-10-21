@extends('layouts.app')

@section('title', 'Gói hội viên')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div><p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Tài khoản của bạn</p><h1 class="gym-title mt-2">Gói hội viên</h1><p class="mt-2 text-slate-600">Các gói được chia theo thời hạn để dễ theo dõi.</p></div>
            <div class="flex flex-wrap gap-2"><a class="gym-btn-outline" href="{{ route('my.attendance.index') }}">Lịch sử tập</a><a class="gym-btn-primary" href="{{ route('packages.index') }}">Xem gói tập</a></div>
        </header>

        <section class="mb-8" aria-labelledby="active-memberships-title">
            <header class="mb-3 flex items-center justify-between gap-3"><h2 class="text-xl font-extrabold" id="active-memberships-title">Đang sử dụng</h2><span class="text-sm text-slate-500">{{ number_format($activeMemberships->total()) }} gói</span></header>
            @include('memberships._cards', ['memberships' => $activeMemberships, 'emptyMessage' => 'Bạn chưa có gói đang hiệu lực.'])
        </section>

        <section class="mb-8" aria-labelledby="upcoming-memberships-title">
            <header class="mb-3 flex items-center justify-between gap-3"><h2 class="text-xl font-extrabold" id="upcoming-memberships-title">Sắp bắt đầu</h2><span class="text-sm text-slate-500">{{ number_format($upcomingMemberships->total()) }} gói</span></header>
            @include('memberships._cards', ['memberships' => $upcomingMemberships, 'emptyMessage' => 'Bạn chưa có gói chờ hiệu lực.'])
        </section>

        <section aria-labelledby="expired-memberships-title">
            <header class="mb-3 flex items-center justify-between gap-3"><h2 class="text-xl font-extrabold" id="expired-memberships-title">Đã hết hạn</h2><span class="text-sm text-slate-500">{{ number_format($expiredMemberships->total()) }} gói</span></header>
            @include('memberships._cards', ['memberships' => $expiredMemberships, 'emptyMessage' => 'Chưa có gói hết hạn.'])
        </section>
    </section>
@endsection
