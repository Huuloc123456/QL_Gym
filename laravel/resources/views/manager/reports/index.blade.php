@extends('layouts.app')

@section('title', 'Báo cáo đơn hàng')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-7 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Khu vực quản lý</p>
                <h1 class="gym-title mt-2">Báo cáo đơn hàng</h1>
                <p class="mt-2 max-w-3xl text-slate-600">Theo dõi đơn được tạo, đơn đã xác nhận và các khoản tiền thực nhận trong khoảng ngày đã chọn.</p>
            </div>
            <a class="font-semibold text-gym-blue underline underline-offset-4" href="{{ route('manager.dashboard') }}">← Tổng quan quản lý</a>
        </header>

        <form class="gym-card grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end" method="GET" action="{{ route('manager.reports.index') }}">
            <div>
                <label class="gym-label" for="from_date">Từ ngày</label>
                <input class="gym-input" id="from_date" name="from_date" type="date" value="{{ old('from_date', request('from_date', $report['from_date'])) }}" required>
            </div>
            <div>
                <label class="gym-label" for="to_date">Đến ngày</label>
                <input class="gym-input" id="to_date" name="to_date" type="date" value="{{ old('to_date', request('to_date', $report['to_date'])) }}" required>
            </div>
            <div class="flex flex-wrap gap-2">
                <button class="gym-btn-primary" type="submit">Xem báo cáo</button>
                <a class="gym-btn-outline" href="{{ route('manager.reports.index') }}">Tháng này</a>
            </div>
        </form>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="gym-card">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Đơn tạo trong kỳ</p>
                <p class="mt-3 text-3xl font-extrabold tabular-nums">{{ number_format(array_sum($report['order_counts'])) }}</p>
                <p class="mt-2 text-sm text-slate-600">{{ number_format($report['order_counts']['pending']) }} chờ · {{ number_format($report['order_counts']['paid']) }} đã thanh toán · {{ number_format($report['order_counts']['cancelled']) }} đã hủy</p>
            </article>
            <article class="gym-card">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Doanh thu đơn đã xác nhận</p>
                <p class="mt-3 text-2xl font-extrabold tabular-nums">{{ number_format($report['confirmed_revenue'], 0, ',', '.') }} VNĐ</p>
                <p class="mt-2 text-sm text-slate-600">Tổng giá trị đơn được xác nhận trong kỳ.</p>
            </article>
            <article class="gym-card">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Tiền thực nhận</p>
                <p class="mt-3 text-2xl font-extrabold tabular-nums">{{ number_format($report['receipts_total'], 0, ',', '.') }} VNĐ</p>
                <p class="mt-2 text-sm text-slate-600">Tiền được ghi nhận thu trong kỳ.</p>
            </article>
            <article class="gym-card">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Theo phương thức</p>
                <p class="mt-3 text-sm font-semibold">Tiền mặt <span class="float-right tabular-nums">{{ number_format($report['receipts_by_method']['cash'], 0, ',', '.') }} VNĐ</span></p>
                <p class="mt-3 text-sm font-semibold">Chuyển khoản <span class="float-right tabular-nums">{{ number_format($report['receipts_by_method']['bank_transfer'], 0, ',', '.') }} VNĐ</span></p>
            </article>
        </div>

        <p class="mt-5 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm leading-6 text-sky-950">
            Doanh thu đơn đã xác nhận và tiền thực nhận có thể khác nhau khi hội viên trả nhiều lần hoặc ở kỳ khác. Số liệu tính theo múi giờ phòng tập; ngày kết thúc được tính trọn ngày.
        </p>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <h2 class="font-bold text-gym-ink">Tải báo cáo chi tiết</h2>
                <p class="mt-1 text-sm text-slate-600">File Excel gồm tổng quan, danh sách đơn và từng khoản thu.</p>
            </div>
            <a class="gym-btn-primary" href="{{ route('manager.reports.export', ['from_date' => $report['from_date'], 'to_date' => $report['to_date']]) }}">Xuất Excel</a>
        </div>
    </section>
@endsection
