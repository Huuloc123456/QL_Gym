@extends('layouts.app')

@section('title', 'Quản lý đơn hội viên')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-8">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Khu vực nhân viên</p>
            <h1 class="gym-title mt-2">Đơn đăng ký gói tập</h1>
            <p class="mt-2 text-slate-600">Ghi từng khoản tiền nhận được, sau đó xác nhận khi đã thu đủ.</p>
        </header>
        <div class="grid gap-5">
            @forelse ($orders as $order)
                @php($received = (int) ($order->payments_sum_amount ?? 0))
                @php($remaining = max(0, $order->amount - $received))
                <article class="gym-card p-5 sm:p-7">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="break-words text-lg font-bold">{{ $order->user->name }} · {{ $order->gymPackage->name }}</h2>
                            <p class="mt-1 text-sm text-slate-600">Đơn #{{ $order->id }} · {{ $order->user->phone }} · Bắt đầu {{ $order->start_date->format('d/m/Y') }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-3 py-2 text-sm font-bold {{ $order->status === 'paid' ? 'bg-emerald-100 text-emerald-900' : ($order->status === 'cancelled' ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-950') }}">{{ $order->status === 'paid' ? 'Đã thanh toán' : ($order->status === 'cancelled' ? 'Đã hủy' : 'Đang chờ') }}</span>
                    </div>

                    <dl class="mt-4 grid gap-3 border-y border-slate-100 py-4 sm:grid-cols-3">
                        <div><dt class="text-sm text-slate-500">Tổng đơn</dt><dd class="mt-1 font-bold tabular-nums">{{ number_format($order->amount, 0, ',', '.') }} VNĐ</dd></div>
                        <div><dt class="text-sm text-slate-500">Đã nhận</dt><dd class="mt-1 font-bold tabular-nums">{{ number_format($received, 0, ',', '.') }} VNĐ</dd></div>
                        <div><dt class="text-sm text-slate-500">Còn phải thu</dt><dd class="mt-1 font-bold tabular-nums">{{ number_format($remaining, 0, ',', '.') }} VNĐ</dd></div>
                    </dl>

                    @if ($order->payments->isNotEmpty())
                        <div class="mt-4">
                            <h3 class="font-bold">Lịch sử nhận tiền</h3>
                            <ul class="mt-2 space-y-1 text-sm text-slate-600">
                                @foreach ($order->payments as $payment)
                                    <li>{{ $payment->received_at->format('d/m/Y H:i') }} · {{ number_format($payment->amount, 0, ',', '.') }} VNĐ · {{ $payment->payment_method === 'cash' ? 'Tiền mặt' : 'Chuyển khoản' }} · {{ $payment->receiver->name }} @if ($payment->reference) · {{ $payment->reference }} @endif</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($order->cancellation_requested_at)
                        <p class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sky-950">Khách đã gửi yêu cầu hủy lúc {{ $order->cancellation_requested_at->format('d/m/Y H:i') }}. Kiểm tra lịch sử tiền nhận trước khi xử lý.</p>
                    @endif
                    @if ($order->status === 'pending' && $order->start_date->toDateString() < now(config('app.timezone'))->toDateString())
                        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-950">Ngày bắt đầu dự kiến đã qua. Hãy chốt với khách một ngày mới từ hôm nay trở đi trước khi xác nhận.</p>
                    @endif

                    @if ($order->status === 'pending')
                        <div class="mt-5 grid gap-5 border-t border-slate-100 pt-5 lg:grid-cols-2">
                            @if ($remaining > 0)
                                <form class="grid gap-3 sm:grid-cols-2" method="POST" action="{{ route('staff.orders.payments.store', $order) }}">
                                    @csrf
                                    <div><label class="gym-label" for="amount-{{ $order->id }}">Số tiền nhận</label><input class="gym-input" id="amount-{{ $order->id }}" type="number" name="amount" min="1" max="{{ $remaining }}" value="{{ $remaining }}" required></div>
                                    <div><label class="gym-label" for="method-{{ $order->id }}">Hình thức</label><select class="gym-input" id="method-{{ $order->id }}" name="payment_method"><option value="bank_transfer">Chuyển khoản</option><option value="cash">Tiền mặt</option></select></div>
                                    <div class="sm:col-span-2"><label class="gym-label" for="reference-{{ $order->id }}">Mã giao dịch (nếu có)</label><input class="gym-input" id="reference-{{ $order->id }}" name="reference" maxlength="255"></div>
                                    <button class="gym-btn-outline sm:col-span-2 sm:justify-self-start" type="submit">Ghi nhận tiền đã nhận</button>
                                </form>
                            @endif
                            <div class="space-y-4">
                                @if ($remaining === 0)
                                    <form class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end" method="POST" action="{{ route('staff.orders.confirm', $order) }}">
                                        @csrf
                                        <div><label class="gym-label" for="start-{{ $order->id }}">Ngày bắt đầu thực tế</label><input class="gym-input" id="start-{{ $order->id }}" type="date" name="start_date" autocomplete="off" min="{{ now(config('app.timezone'))->toDateString() }}" value="{{ $order->start_date->toDateString() >= now(config('app.timezone'))->toDateString() ? $order->start_date->toDateString() : '' }}" required></div>
                                        <button class="gym-btn-primary" type="submit">Xác nhận gói</button>
                                    </form>
                                @endif
                                @if ($received === 0)
                                    <form method="POST" action="{{ route('staff.orders.cancel', $order) }}" onsubmit="return confirm('Hủy đơn đăng ký này?')">
                                        @csrf
                                        <button class="min-h-11 rounded-lg px-2 py-2 text-sm font-semibold text-red-700 underline underline-offset-4 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-red-600" type="submit">Hủy đơn chưa nhận tiền</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @elseif ($order->membership)
                        <p class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-950">Gói đã hiệu lực từ {{ $order->membership->starts_on->format('d/m/Y') }} đến hết {{ $order->membership->expires_on->copy()->subDay()->format('d/m/Y') }}.</p>
                    @endif
                </article>
            @empty
                <div class="gym-card py-10 text-center text-slate-600">Chưa có đơn đăng ký nào.</div>
            @endforelse
        </div>
    </section>
@endsection

