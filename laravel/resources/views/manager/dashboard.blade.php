@extends('layouts.app')

@section('title', 'Tổng quan quản lý')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-7">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Khu vực quản lý</p>
            <h1 class="gym-title mt-2">Tổng quan The Gym</h1>
            <p class="mt-2 text-slate-600">Tình hình hội viên và hoạt động trong ngày.</p><a class="gym-btn-outline mt-4" href="{{ route('manager.reports.index') }}">Xem báo cáo theo thời gian</a>
        </header>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
            <article class="gym-card"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Doanh thu đã xác nhận</p><p class="mt-3 text-2xl font-extrabold tabular-nums">{{ number_format($allInvoiceRevenue, 0, ',', '.') }} VNĐ</p></article>
            <article class="gym-card"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Hội viên</p><p class="mt-3 text-4xl font-extrabold tabular-nums">{{ number_format($customerCount) }}</p></article>
            <article class="gym-card"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Gói đang hiệu lực</p><p class="mt-3 text-4xl font-extrabold tabular-nums">{{ number_format($activeMembershipCount) }}</p></article>
            <article class="gym-card"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Đơn chờ xác nhận</p><p class="mt-3 text-4xl font-extrabold tabular-nums">{{ number_format($pendingOrderCount) }}</p><a class="mt-3 inline-block font-semibold text-gym-blue underline underline-offset-4" href="{{ route('gym.invoices.index', ['status' => 'Chưa thanh toán']) }}">Mở danh sách đơn</a></article>
            <article class="gym-card"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Lượt đang tập</p><p class="mt-3 text-4xl font-extrabold tabular-nums">{{ number_format($openVisitCount) }}</p><a class="mt-3 inline-block font-semibold text-gym-blue underline underline-offset-4" href="{{ route('staff.checkins.index') }}">Mở check-in</a></article>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-3"><a class="gym-card" href="{{ route('gym.admin.index', 'products') }}"><p class="text-sm text-slate-500">Sản phẩm</p><p class="mt-2 text-2xl font-bold">{{ $productCount }}</p></a><a class="gym-card" href="{{ route('gym.admin.index', 'products') }}"><p class="text-sm text-slate-500">Sản phẩm tồn không quá 5</p><p class="mt-2 text-2xl font-bold">{{ $lowStockCount }}</p></a><a class="gym-card" href="{{ route('gym.invoices.index', ['status' => 'Chưa thanh toán']) }}"><p class="text-sm text-slate-500">Hóa đơn chờ thanh toán</p><p class="mt-2 text-2xl font-bold">{{ $invoicePendingCount }}</p></a></div>
        <div class="mt-8 grid gap-7 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
            <section class="min-w-0">
                <h2 class="mb-3 text-xl font-extrabold uppercase">Gói đang mở bán</h2>
                <div class="grid gap-3">
                    @forelse ($packages as $package)
                        <article class="gym-card flex items-center justify-between gap-3"><div><h3 class="font-bold">{{ $package->name }}</h3><p class="text-sm text-slate-500">{{ $package->duration_months }} tháng</p></div><strong class="whitespace-nowrap tabular-nums">{{ number_format($package->price, 0, ',', '.') }} VNĐ</strong></article>
                    @empty
                        <p class="text-slate-600">Chưa có gói tập đang mở bán.</p>
                    @endforelse
                </div>
            </section>
            <section class="min-w-0">
                <h2 class="mb-3 text-xl font-extrabold uppercase">Đăng ký gần đây</h2>
                <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm" tabindex="0" aria-label="Bảng đăng ký gần đây; vuốt ngang để xem thêm cột">
                    <table class="gym-table min-w-[36rem]">
                        <thead><tr><th scope="col">Hội viên</th><th scope="col">Gói</th><th scope="col">Số tiền</th><th scope="col">Trạng thái</th></tr></thead>
                        <tbody>
                            @foreach ($recentInvoices as $invoice)
                                <tr><td>{{ $invoice->TENKH }}</td><td><a class="text-gym-blue underline" href="{{ route('gym.invoices.show', $invoice->MAHD) }}">Hóa đơn #{{ $invoice->MAHD }}</a></td><td>{{ number_format($invoice->THANHTIEN, 0, ',', '.') }} VNĐ</td><td>{{ $invoice->TRANGTHAI }}</td></tr>
                            @endforeach
                            @forelse ($recentOrders as $order)
                                <tr><td>{{ $order->user->name }}</td><td>{{ $order->gymPackage->name }}</td><td class="whitespace-nowrap tabular-nums">{{ number_format($order->amount, 0, ',', '.') }} VNĐ</td><td>{{ $order->status === 'paid' ? 'Đã thanh toán' : ($order->status === 'cancelled' ? 'Đã hủy' : 'Chờ thanh toán') }}</td></tr>
                            @empty
                                @if ($recentInvoices->isEmpty())<tr><td colspan="4" class="py-8 text-center text-slate-500">Chưa có đơn đăng ký.</td></tr>@endif
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </section>
@endsection
