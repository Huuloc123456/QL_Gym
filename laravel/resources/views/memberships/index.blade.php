@extends('layouts.app')

@section('title', 'Gói hội viên')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div><p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Tài khoản của bạn</p><h1 class="gym-title mt-2">Gói hội viên</h1><p class="mt-2 text-slate-600">Theo dõi thời hạn sử dụng các gói tập của bạn.</p></div>
            <div class="flex flex-wrap gap-2"><a class="gym-btn-outline" href="{{ route('my.attendance.index') }}">Lịch sử tập</a><a class="gym-btn-primary" href="{{ route('packages.index') }}">Xem gói tập</a></div>
        </header>
        <div class="grid gap-4">
            @foreach ($sqlMemberships as $registration)
                <article class="gym-card flex flex-wrap items-center justify-between gap-4"><div><h2 class="text-xl font-bold">{{ $registration->TENGOI }} @if ($registration->trial_request_id)<span class="text-sm font-medium text-gym-blue">· Tập thử</span>@endif</h2><p class="mt-2 text-sm text-slate-600">Bắt đầu {{ substr($registration->NGAYBATDAU, 0, 10) }} · Tập đến hết {{ substr($registration->NGAYKETTHUC, 0, 10) }}</p></div><span class="rounded-full bg-sky-50 px-4 py-2 text-sm font-semibold text-gym-blue">{{ $registration->TRANGTHAI !== 'Còn hiệu lực' ? $registration->TRANGTHAI : (\Carbon\Carbon::parse($registration->NGAYKETTHUC)->isPast() ? 'Hết hạn' : (\Carbon\Carbon::parse($registration->NGAYBATDAU)->isFuture() ? 'Chờ hiệu lực' : 'Đang hiệu lực')) }}</span></article>
            @endforeach
            @forelse ($memberships as $membership)
                @php($today = now(config('app.timezone'))->toDateString())
                @php($isActive = $membership->starts_on->toDateString() <= $today && $membership->expires_on->toDateString() > $today)
                @php($isUpcoming = $membership->starts_on->toDateString() > $today)
                <article class="gym-card flex flex-wrap items-center justify-between gap-4">
                    <div><h2 class="text-xl font-bold">{{ $membership->gymPackage->name }}</h2><p class="mt-1 text-slate-600">Bắt đầu {{ $membership->starts_on->format('d/m/Y') }} · Tập đến hết {{ $membership->expires_on->copy()->subDay()->format('d/m/Y') }}</p></div>
                    <span class="rounded-full px-4 py-2 text-sm font-bold {{ $isActive ? 'bg-emerald-100 text-emerald-900' : ($isUpcoming ? 'bg-sky-100 text-sky-900' : 'bg-slate-100 text-slate-700') }}">{{ $isActive ? 'Đang hiệu lực' : ($isUpcoming ? 'Chờ hiệu lực' : 'Đã hết hạn') }}</span>
                </article>
            @empty
                @if ($sqlMemberships->isEmpty())
                <div class="gym-card py-10 text-center"><h2 class="text-xl font-bold">Bạn chưa có gói tập</h2><p class="mt-2 text-slate-600">Chọn một gói để bắt đầu hành trình tại The Gym.</p><a class="gym-btn-primary mt-5" href="{{ route('packages.index') }}">Chọn gói tập</a></div>
                @endif
            @endforelse
        </div>
    </section>
@endsection

