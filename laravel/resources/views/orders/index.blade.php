@extends('layouts.app')

@section('title', 'Đơn đăng ký')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div><p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Tài khoản của bạn</p><h1 class="gym-title mt-2">Đơn đăng ký gói tập</h1><p class="mt-2 text-slate-600">Nhân viên sẽ cập nhật khi nhận và xác nhận thanh toán.</p></div>
            <a class="gym-btn-primary" href="{{ route('packages.index') }}">Đăng ký gói mới</a>
        </header>
        <div class="grid gap-4">
            @forelse ($orders as $order)
                @php($received = (int) ($order->payments_sum_amount ?? 0))
                <article class="gym-card">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h2 class="text-xl font-bold"><a class="break-words text-gym-ink underline decoration-slate-300 underline-offset-4 hover:text-gym-blue" href="{{ route('my.orders.show', $order) }}">{{ $order->gymPackage->name }}</a></h2>
                            <p class="mt-2 text-sm text-slate-600">Ngày bắt đầu dự kiến {{ $order->start_date->format('d/m/Y') }} · {{ number_format($order->amount, 0, ',', '.') }} VNĐ</p>
                            <p class="mt-1 text-sm text-slate-600">Đã ghi nhận {{ number_format($received, 0, ',', '.') }} VNĐ · {{ $order->payment_method === 'cash' ? 'Tiền mặt' : 'Chuyển khoản' }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-3 py-2 text-sm font-bold {{ $order->status === 'paid' ? 'bg-emerald-100 text-emerald-900' : ($order->status === 'cancelled' ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-950') }}">{{ $order->status === 'paid' ? 'Đã xác nhận' : ($order->status === 'cancelled' ? 'Đã hủy' : 'Chờ xác nhận thanh toán') }}</span>
                    </div>
                    @if ($order->status === 'pending')
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                            @if ($order->cancellation_requested_at)
                                <p class="text-sm text-slate-600">Đã gửi yêu cầu hủy ngày {{ $order->cancellation_requested_at->format('d/m/Y H:i') }}. Nhân viên sẽ kiểm tra khoản đã nhận.</p>
                            @else
                                <p class="text-sm text-slate-600">Đơn chỉ được hủy sau khi nhân viên kiểm tra chưa nhận tiền.</p>
                                <form method="POST" action="{{ route('my.orders.cancel-request', $order) }}" onsubmit="return confirm('Gửi yêu cầu hủy đơn này cho nhân viên?')">
                                    @csrf
                                    <button class="gym-btn-outline min-h-10 border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" type="submit">Yêu cầu hủy đơn</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <div class="gym-card py-10 text-center"><p class="text-slate-600">Bạn chưa đăng ký gói tập nào.</p><a class="gym-btn-primary mt-5" href="{{ route('packages.index') }}">Xem gói tập</a></div>
            @endforelse
        </div>
    </section>
@endsection

