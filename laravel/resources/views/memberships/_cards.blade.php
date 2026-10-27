<div class="grid gap-3">
    @forelse ($memberships as $membership)
        @php($status = $membership->sql_membership_status === 'Đã hủy' ? 'Đã hủy' : ($membership->sql_membership_status === 'Hết hạn' || $membership->expires_on->toDateString() <= $today ? 'Đã hết hạn' : ($membership->starts_on->toDateString() > $today ? 'Chờ hiệu lực' : 'Đang hiệu lực')))
        <article class="gym-card flex flex-wrap items-center justify-between gap-4">
            <div><h3 class="text-lg font-bold">{{ $membership->gymPackage->name }}</h3><p class="mt-1 text-sm text-slate-600">Bắt đầu <time datetime="{{ $membership->starts_on->toDateString() }}">{{ $membership->starts_on->format('d/m/Y') }}</time> · Tập đến hết <time datetime="{{ $membership->expires_on->copy()->subDay()->toDateString() }}">{{ $membership->expires_on->copy()->subDay()->format('d/m/Y') }}</time></p></div>
            <span class="rounded-full px-4 py-2 text-sm font-bold {{ $status === 'Đang hiệu lực' ? 'bg-emerald-100 text-emerald-900' : ($status === 'Chờ hiệu lực' ? 'bg-sky-100 text-sky-900' : ($status === 'Đã hủy' ? 'bg-red-100 text-red-900' : 'bg-slate-100 text-slate-700')) }}">{{ $status }}</span>
        </article>
    @empty
        <div class="gym-card py-6 text-center text-slate-600">{{ $emptyMessage }}</div>
    @endforelse
</div>
@if ($memberships->hasPages())
    <nav class="mt-4" aria-label="Các trang nhóm gói hội viên">{{ $memberships->links() }}</nav>
@endif
