<div class="grid gap-3">
    @forelse ($memberships as $membership)
        <article class="gym-card flex flex-wrap items-center justify-between gap-4">
            <div><h3 class="text-lg font-bold">{{ $membership->gymPackage->name }}</h3><p class="mt-1 text-sm text-slate-600">Bắt đầu <time datetime="{{ $membership->starts_on->toDateString() }}">{{ $membership->starts_on->format('d/m/Y') }}</time> · Tập đến hết <time datetime="{{ $membership->expires_on->copy()->subDay()->toDateString() }}">{{ $membership->expires_on->copy()->subDay()->format('d/m/Y') }}</time></p></div>
            <span class="rounded-full px-4 py-2 text-sm font-bold {{ $membership->starts_on->toDateString() <= $today && $membership->expires_on->toDateString() > $today ? 'bg-emerald-100 text-emerald-900' : ($membership->starts_on->toDateString() > $today ? 'bg-sky-100 text-sky-900' : 'bg-slate-100 text-slate-700') }}">{{ $membership->starts_on->toDateString() <= $today && $membership->expires_on->toDateString() > $today ? 'Đang hiệu lực' : ($membership->starts_on->toDateString() > $today ? 'Chờ hiệu lực' : 'Đã hết hạn') }}</span>
        </article>
    @empty
        <div class="gym-card py-6 text-center text-slate-600">{{ $emptyMessage }}</div>
    @endforelse
</div>
@if ($memberships->hasPages())
    <nav class="mt-4" aria-label="Các trang nhóm gói hội viên">{{ $memberships->links() }}</nav>
@endif
