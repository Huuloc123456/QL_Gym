@extends('layouts.app')

@section('title', 'Gói hội viên')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div><p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Tài khoản của bạn</p><h1 class="gym-title mt-2">Gói hội viên</h1><p class="mt-2 text-slate-600">Các gói được chia theo thời hạn để dễ theo dõi.</p></div>
            <div class="flex flex-wrap gap-2"><a class="gym-btn-outline" href="{{ route('my.attendance.index') }}">Lịch sử tập</a><a class="gym-btn-primary" href="{{ route('packages.index') }}">Xem gói tập</a></div>
        </header>

        @if ($sqlMemberships->total() > 0)
            <section class="mb-8" aria-labelledby="legacy-memberships-title">
                <header class="mb-3 flex items-center justify-between gap-3"><h2 class="text-xl font-extrabold" id="legacy-memberships-title">Gói từ lịch sử phòng tập</h2><span class="text-sm text-slate-500">{{ number_format($sqlMemberships->total()) }} gói</span></header>
                <div class="grid gap-3">
                    @foreach ($sqlMemberships as $registration)
                        @php($startsOn = \Illuminate\Support\Carbon::parse($registration->NGAYBATDAU, config('app.timezone')))
                        @php($endsOn = \Illuminate\Support\Carbon::parse($registration->NGAYKETTHUC, config('app.timezone')))
                        @php($status = $registration->TRANGTHAI !== 'Còn hiệu lực' ? $registration->TRANGTHAI : ($endsOn->isPast() ? 'Hết hạn' : ($startsOn->isFuture() ? 'Chờ hiệu lực' : 'Đang hiệu lực')))
                        <article class="gym-card flex flex-wrap items-center justify-between gap-4">
                            <div><h3 class="text-lg font-bold">{{ $registration->TENGOI }} @if ($registration->trial_request_id)<span class="text-sm font-medium text-gym-blue">· Tập thử</span>@endif</h3><p class="mt-2 text-sm text-slate-600">Bắt đầu {{ $startsOn->toDateString() }} · Tập đến hết {{ $endsOn->toDateString() }}</p></div>
                            <span class="rounded-full bg-sky-50 px-4 py-2 text-sm font-semibold text-gym-blue">{{ $status }}</span>
                        </article>
                    @endforeach
                </div>
                <nav class="mt-4" aria-label="Các trang gói tập lịch sử">{{ $sqlMemberships->links() }}</nav>
            </section>
        @endif

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
