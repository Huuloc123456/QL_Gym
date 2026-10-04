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
                <div><label class="gym-label" for="member">Mã hội viên hoặc số điện thoại</label><input class="gym-input" id="member" name="member" type="text" inputmode="numeric" autocomplete="off" value="{{ old('member') }}" placeholder="Ví dụ: 0901234567" required></div>
                <button class="gym-btn-primary w-full sm:w-auto" type="submit">Ghi nhận vào tập</button>
            </form>
        </div>

        <h2 class="mb-3 text-xl font-extrabold uppercase">Lịch sử lượt tập</h2>
        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm" tabindex="0" aria-label="Bảng lượt tập; vuốt ngang để xem thêm cột">
            <table class="gym-table">
                <thead><tr><th scope="col">Hội viên</th><th scope="col">Gói</th><th scope="col">Giờ vào</th><th scope="col">Giờ ra</th><th scope="col">Thao tác</th></tr></thead>
                <tbody>
                    @forelse ($visits as $visit)
                        <tr>
                            <td><strong>{{ $visit->user->name }}</strong><div class="text-xs text-slate-500">{{ $visit->user->phone }}</div></td>
                            <td>{{ $visit->membership->gymPackage->name }}</td>
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
                                        <input class="gym-input min-h-9 min-w-0 px-2 py-1 text-sm" id="reason-{{ $visit->id }}" name="reason" placeholder="Lý do đóng thủ công" required>
                                        <button class="min-h-9 shrink-0 rounded-lg border border-slate-300 px-3 py-1 text-sm font-semibold hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-gym-blue" type="submit">Đóng lượt</button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-500">{{ $visit->closedBy?->name ?? 'Đã kết thúc' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-slate-500">Chưa có lượt tập nào được ghi nhận.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

