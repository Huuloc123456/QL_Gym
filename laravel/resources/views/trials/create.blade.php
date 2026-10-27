@extends('layouts.app')

@section('title', 'Đăng ký tập thử')

@section('content')
    <section class="gym-container max-w-3xl py-10 sm:py-14">
        <header class="mb-6">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Trải nghiệm The Gym</p>
            <h1 class="gym-title mt-2">Đăng ký tập thử</h1>
            <p class="mt-2 text-slate-600">Gửi ngày muốn đến. Nhân viên sẽ liên hệ, xác minh và duyệt trước khi bạn vào tập.</p>
        </header>
        <div class="gym-card space-y-4">
            <div><p class="font-bold">{{ auth()->user()->name }}</p><p class="text-sm text-slate-600">{{ auth()->user()->phone }} · {{ auth()->user()->email }}</p></div>
            <a class="inline-block text-sm font-semibold text-gym-blue underline" href="{{ route('my.profile.show') }}">Cập nhật thông tin cá nhân</a>
            <form class="grid gap-4 border-t border-slate-200 pt-4" method="POST" action="{{ route('trials.store') }}">
                @csrf
                <div><label class="gym-label" for="desired_date">Ngày muốn tập thử</label><x-form-input id="desired_date" name="desired_date" type="date" min="{{ now()->toDateString() }}" value="{{ old('desired_date', now()->toDateString()) }}" required /></div>
                <div><button class="gym-btn-primary" type="submit">Gửi yêu cầu tập thử</button></div>
            </form>
        </div>
        <h2 class="mt-8 text-xl font-bold">Yêu cầu của bạn</h2>
        <div class="mt-4 grid gap-4">
            @forelse ($trials as $trial)
                <article class="gym-card space-y-2">
                    <p class="font-bold">{{ match ($trial->status) { 'pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Đã từ chối', 'cancelled' => 'Đã hủy', default => 'Cần đối chiếu' } }}</p>
                    <p class="text-sm text-slate-600">Ngày mong muốn: {{ $trial->desired_date?->format('d/m/Y') ?? 'Chưa có' }}</p>
                    @if ($trial->entitlement)
                        <p class="text-sm">Thời gian thử: {{ $trial->entitlement->starts_at->format('d/m/Y') }} – {{ $trial->entitlement->ends_at->copy()->subSecond()->format('d/m/Y') }}</p>
                        <p class="text-sm text-slate-600">{{ $trial->entitlement->revoked_at ? 'Quyền thử đã được thu hồi.' : (now()->gte($trial->entitlement->ends_at) ? 'Quyền thử đã hết hạn.' : (now()->lt($trial->entitlement->starts_at) ? 'Chưa đến ngày bắt đầu.' : 'Có thể đến quầy để check-in.')) }}</p>
                    @endif
                    @if ($trial->decision_reason)<p class="text-sm">Lý do: {{ $trial->decision_reason }}</p>@endif
                    @if ($trial->status === 'pending')<form method="POST" action="{{ route('trials.cancel', $trial) }}">@csrf<button class="gym-btn-outline" type="submit">Hủy yêu cầu</button></form>@endif
                </article>
            @empty
                <p class="text-sm text-slate-600">Bạn chưa gửi yêu cầu tập thử.</p>
            @endforelse
        </div>
        <div class="mt-6">{{ $trials->links() }}</div>
    </section>
@endsection
