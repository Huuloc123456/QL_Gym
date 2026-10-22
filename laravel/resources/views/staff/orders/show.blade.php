@extends('layouts.app')

@section('title', 'Chi tiết đơn hội viên #'.$order->id)

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <a class="inline-flex min-h-11 items-center font-semibold text-gym-blue underline underline-offset-4 hover:text-sky-800" href="{{ route('gym.invoices.index', ['source' => 'package_order']) }}">← Danh sách đơn hàng và hóa đơn</a>

        <header class="mb-6 mt-5">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Khu vực nhân viên</p>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h1 class="gym-title">Chi tiết đơn hội viên #{{ $order->id }}</h1>
                <span class="rounded-full px-3 py-1.5 text-sm font-bold {{ $order->status === 'paid' ? 'bg-emerald-100 text-emerald-900' : ($order->status === 'cancelled' ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-950') }}">{{ $order->status === 'paid' ? 'Đã thanh toán' : ($order->status === 'cancelled' ? 'Đã hủy' : 'Đang chờ') }}</span>
            </div>
            <p class="mt-2 text-slate-600">{{ $order->user->name }} · {{ $order->gymPackage->name }} · {{ number_format($order->amount, 0, ',', '.') }} VNĐ</p>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <p class="text-sm text-slate-600">Mỗi thao tác đều kiểm tra trạng thái mới nhất trước khi lưu.</p>
                <a class="gym-btn-outline" href="{{ route('staff.orders.show', $order) }}">Tải lại trạng thái</a>
            </div>
        </header>

        @if ($order->status === 'pending' && $order->start_date->toDateString() < now(config('app.timezone'))->toDateString())
            <p class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-950">Ngày bắt đầu dự kiến đã qua. Hãy chốt với khách một ngày mới từ hôm nay trở đi trước khi xác nhận.</p>
        @endif

        <article class="gym-card p-5 sm:p-7">
            @include('gym.package-order-actions', ['order' => $order])
        </article>
    </section>
@endsection
