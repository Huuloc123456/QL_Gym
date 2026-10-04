@extends('layouts.app')

@section('title', 'Chi tiết đơn')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <a class="font-semibold text-gym-blue underline underline-offset-4 hover:text-gym-ink" href="{{ route('my.orders.index') }}">← Tất cả đơn đăng ký</a>
        <article class="gym-card mx-auto mt-5 max-w-4xl p-6 sm:p-9">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Đơn #{{ $order->id }}</p><h1 class="gym-title mt-2">{{ $order->gymPackage->name }}</h1><p class="mt-2 text-sm text-slate-600">Tạo ngày {{ $order->created_at->format('d/m/Y H:i') }}</p></div>
                <span class="rounded-full px-3 py-2 text-sm font-bold {{ $order->status === 'paid' ? 'bg-emerald-100 text-emerald-900' : ($order->status === 'cancelled' ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-950') }}">{{ $order->status === 'paid' ? 'Đã xác nhận' : ($order->status === 'cancelled' ? 'Đã hủy' : 'Đang chờ') }}</span>
            </div>
            <dl class="mt-6 grid gap-3 border-y border-slate-100 py-5 sm:grid-cols-3">
                <div><dt class="text-sm text-slate-500">Tổng tiền</dt><dd class="mt-1 font-bold tabular-nums">{{ number_format($order->amount, 0, ',', '.') }} VNĐ</dd></div>
                <div><dt class="text-sm text-slate-500">Ngày bắt đầu dự kiến</dt><dd class="mt-1 font-semibold">{{ $order->start_date->format('d/m/Y') }}</dd></div>
                <div><dt class="text-sm text-slate-500">Phương thức dự kiến</dt><dd class="mt-1 font-semibold">{{ $order->payment_method === 'cash' ? 'Tiền mặt' : 'Chuyển khoản' }}</dd></div>
            </dl>
            @if ($order->status === 'pending')
                <p class="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-4 leading-6 text-sky-950">{{ $order->payment_method === 'cash' ? 'Bạn có thể thanh toán tiền mặt tại quầy.' : 'Vui lòng liên hệ quầy để nhận hướng dẫn chuyển khoản.' }} Nhân viên sẽ ghi nhận khoản đã nhận; chọn phương thức thanh toán không tự kích hoạt gói.</p>
            @endif
            <h2 class="mt-6 text-lg font-bold">Các khoản đã nhận</h2>
            <div class="mt-2 divide-y divide-slate-100">
                @forelse ($order->payments as $payment)
                    <div class="flex flex-wrap justify-between gap-2 py-3"><span class="text-sm text-slate-600">{{ $payment->received_at->format('d/m/Y H:i') }} · {{ $payment->receiver->name }}</span><strong class="tabular-nums">{{ number_format($payment->amount, 0, ',', '.') }} VNĐ</strong></div>
                @empty
                    <p class="py-3 text-sm text-slate-500">Chưa có khoản thanh toán nào được nhân viên ghi nhận.</p>
                @endforelse
            </div>
            @if ($order->membership)
                <p class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 font-medium text-emerald-950">Gói đã kích hoạt từ {{ $order->membership->starts_on->format('d/m/Y') }} đến hết {{ $order->membership->expires_on->copy()->subDay()->format('d/m/Y') }}.</p>
            @endif
        </article>
    </section>
@endsection

