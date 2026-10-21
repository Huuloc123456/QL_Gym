@extends('layouts.app')

@section('title', 'Check-in hội viên')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-7">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Khu vực nhân viên</p>
            <h1 class="gym-title mt-2">Check-in hội viên</h1>
            <p class="mt-2 text-slate-600">Tra cứu bằng mã hội viên hoặc số điện thoại. Chỉ gói đã thanh toán và đang hiệu lực mới được vào tập.</p>
        </header>
        <div class="gym-card mb-8">
            <form class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end" method="POST" action="{{ route('staff.checkins.store') }}">
                @csrf
                <div><label class="gym-label" for="member">Mã hội viên hoặc số điện thoại</label><x-form-input id="member" name="member" type="text" inputmode="numeric" autocomplete="off" value="{{ old('member') }}" placeholder="Ví dụ: 0901234567" required /></div>
                <button class="gym-btn-primary w-full sm:w-auto" type="submit">Ghi nhận vào tập</button>
            </form>
        </div>

        @if ($filters['status'] !== 'open')
            <section class="gym-card mb-8" aria-labelledby="open-visits-title">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-xl font-extrabold" id="open-visits-title">Hội viên đang tập</h2>
                    <span class="text-sm text-slate-500">{{ number_format($openVisits->count()) }} lượt chưa ghi giờ ra</span>
                </div>
                @forelse ($openVisits as $openVisit)
                    <article class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 py-4 first:border-0 first:pt-0 last:pb-0">
                        <div>
                            <p class="font-bold">{{ $openVisit->user->name }} <span class="font-normal text-slate-500">· {{ $openVisit->user->phone }}</span></p>
                            <p class="mt-1 text-sm text-slate-600">{{ $openVisit->membership?->gymPackage?->name ?? 'Gói từ dữ liệu SQL' }} · Vào {{ $openVisit->checked_in_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('staff.checkins.checkout', $openVisit) }}">@csrf<button class="gym-btn-outline min-h-9 px-3 py-1.5 text-sm" type="submit">Ghi giờ ra</button></form>
                            <form class="flex gap-2" method="POST" action="{{ route('staff.checkins.manual-close', $openVisit) }}" onsubmit="return confirm('Đóng lượt tập này và lưu lý do điều chỉnh?')">
                                @csrf
                                <label class="sr-only" for="open-reason-{{ $openVisit->id }}">Lý do đóng thủ công</label>
                                <x-form-input class="min-w-0 px-2 py-1 text-sm" id="open-reason-{{ $openVisit->id }}" name="reason" placeholder="Lý do đóng thủ công" required />
                                <button class="min-h-9 shrink-0 rounded-lg border border-slate-300 px-3 py-1 text-sm font-semibold hover:bg-slate-50" type="submit">Đóng lượt</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <p class="border-t border-slate-200 pt-4 text-sm text-slate-500">Không có lượt tập đang mở.</p>
                @endforelse
            </section>
        @endif

        <div class="mb-4 flex flex-wrap items-center gap-2" aria-label="Lọc trạng thái lượt tập">
            @foreach (['open' => 'Đang tập', 'history' => 'Lịch sử', 'all' => 'Tất cả'] as $status => $label)
                <a class="rounded-full px-4 py-2 text-sm font-semibold {{ $filters['status'] === $status ? 'bg-gym-blue text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}" href="{{ route('staff.checkins.index', array_merge(request()->except('page'), ['status' => $status])) }}" @if ($filters['status'] === $status) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </div>
        <form class="gym-card mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(14rem,1fr)_minmax(10rem,0.7fr)_minmax(10rem,0.7fr)_auto] xl:items-end" method="GET" action="{{ route('staff.checkins.index') }}">
            <input type="hidden" name="status" value="{{ $filters['status'] }}">
            <div><label class="gym-label" for="q">Tìm hội viên</label><x-form-input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Mã, tên hoặc số điện thoại" /></div>
            <div><label class="gym-label" for="from_date">Từ ngày</label><x-form-input id="from_date" name="from_date" type="date" value="{{ $filters['from_date'] ?? '' }}" /></div>
            <div><label class="gym-label" for="to_date">Đến ngày</label><x-form-input id="to_date" name="to_date" type="date" value="{{ $filters['to_date'] ?? '' }}" /></div>
            <div class="flex gap-2"><button class="gym-btn-primary" type="submit">Tìm</button><a class="gym-btn-outline" href="{{ route('staff.checkins.index', ['status' => $filters['status']]) }}">Xóa lọc</a></div>
        </form>

        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-xl font-extrabold uppercase">{{ $filters['status'] === 'open' ? 'Hội viên đang tập' : ($filters['status'] === 'history' ? 'Lịch sử lượt tập' : 'Tất cả lượt tập') }}</h2>
            <p class="text-sm text-slate-600">{{ number_format($visits->total()) }} lượt · {{ $visits->firstItem() ?? 0 }}–{{ $visits->lastItem() ?? 0 }}</p>
        </div>
        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm" tabindex="0" aria-label="Bảng lượt tập; vuốt ngang để xem thêm cột">
            <table class="gym-table">
                <thead><tr><th scope="col">Hội viên</th><th scope="col">Gói</th><th scope="col">Giờ vào</th><th scope="col">Giờ ra</th><th scope="col">Thao tác</th></tr></thead>
                <tbody>
                    @forelse ($visits as $visit)
                        <tr>
                            <td><strong>{{ $visit->user->name }}</strong><div class="text-xs text-slate-500">{{ $visit->user->phone }}</div></td>
                            <td>{{ $visit->membership?->gymPackage?->name ?? 'Gói từ dữ liệu SQL' }}</td>
                            <td class="whitespace-nowrap">{{ $visit->checked_in_at->format('d/m/Y H:i') }}</td>
                            <td>
                                @if ($visit->checked_out_at)
                                    <span class="whitespace-nowrap">{{ $visit->checked_out_at->format('d/m/Y H:i') }}</span>
                                    @if ($visit->manual_close_reason)<p class="mt-1 max-w-xs whitespace-normal text-xs text-slate-500">Đóng thủ công: {{ $visit->manual_close_reason }}</p>@endif
                                @else
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-900">Đang tập</span>
                                @endif
                            </td>
                            <td class="min-w-56">
                                @if (! $visit->checked_out_at)
                                    <form method="POST" action="{{ route('staff.checkins.checkout', $visit) }}">@csrf<button class="gym-btn-outline min-h-9 px-3 py-1.5 text-sm" type="submit">Ghi giờ ra</button></form>
                                    <form class="mt-2 flex gap-2" method="POST" action="{{ route('staff.checkins.manual-close', $visit) }}" onsubmit="return confirm('Đóng lượt tập này và lưu lý do điều chỉnh?')">
                                        @csrf
                                        <label class="sr-only" for="reason-{{ $visit->id }}">Lý do đóng thủ công</label>
                                        <x-form-input class="min-w-0 px-2 py-1 text-sm" id="reason-{{ $visit->id }}" name="reason" placeholder="Lý do đóng thủ công" required />
                                        <button class="min-h-9 shrink-0 rounded-lg border border-slate-300 px-3 py-1 text-sm font-semibold hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-gym-blue" type="submit">Đóng lượt</button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-500">{{ $visit->closedBy?->name ?? 'Đã kết thúc' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-slate-500">{{ $filters['status'] === 'open' ? 'Không có hội viên nào đang tập.' : 'Không tìm thấy lượt tập phù hợp.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <nav class="mt-6" aria-label="Các trang lượt tập">{{ $visits->links() }}</nav>
    </section>
@endsection

