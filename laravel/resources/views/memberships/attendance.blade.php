@extends('layouts.app')

@section('title', 'Lịch sử tập')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-7 flex flex-wrap items-end justify-between gap-4">
            <div><p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Tài khoản của bạn</p><h1 class="gym-title mt-2">Lịch sử tập</h1><p class="mt-2 text-slate-600">Chỉ bạn mới xem được các lượt vào và ra của mình.</p></div>
            <a class="gym-btn-outline" href="{{ route('my.memberships.index') }}">Gói hội viên</a>
        </header>
        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm" tabindex="0" aria-label="Bảng lịch sử tập; vuốt ngang để xem thêm cột">
            <table class="gym-table">
                <thead><tr><th scope="col">Ngày</th><th scope="col">Gói</th><th scope="col">Giờ vào</th><th scope="col">Giờ ra</th><th scope="col">Ghi chú</th></tr></thead>
                <tbody>
                    @forelse ($visits as $visit)
                        <tr><td class="whitespace-nowrap">{{ $visit->checked_in_at->format('d/m/Y') }}</td><td>{{ $visit->membership->gymPackage->name }}</td><td class="whitespace-nowrap">{{ $visit->checked_in_at->format('H:i') }}</td><td class="whitespace-nowrap">{{ $visit->checked_out_at?->format('H:i') ?? 'Đang tập' }}</td><td>{{ $visit->manual_close_reason ?: '—' }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-slate-500">Bạn chưa có lượt tập nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $visits->links() }}</div>
    </section>
@endsection

