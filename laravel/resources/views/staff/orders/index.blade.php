@extends('layouts.app')

@section('title', 'Quản lý đơn hội viên')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-8">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Khu vực nhân viên</p>
            <h1 class="gym-title mt-2">Đơn hội viên</h1>
            <p class="mt-2 text-slate-600">Tìm đơn theo mã hoặc thông tin khách; đơn đã thanh toán và đã hủy được lưu trong lịch sử.</p>
        </header>

        <nav class="mb-5 flex flex-wrap gap-2" aria-label="Lọc đơn theo trạng thái">
            @foreach (['pending' => 'Cần xử lý', 'history' => 'Lịch sử', 'all' => 'Tất cả'] as $status => $label)
                @php($tabQuery = array_filter(['q' => $filters['q'] ?? null, 'from_date' => $filters['from_date'] ?? null, 'to_date' => $filters['to_date'] ?? null, 'status' => $status], fn ($value) => $value !== null && $value !== ''))
                <a class="{{ ($filters['status'] ?? 'pending') === $status ? 'gym-btn-primary' : 'gym-btn-outline' }}" href="{{ route('staff.orders.index', $tabQuery) }}" @if (($filters['status'] ?? 'pending') === $status) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <form class="gym-card mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end" method="GET" action="{{ route('staff.orders.index') }}">
            <input type="hidden" name="status" value="{{ $filters['status'] }}">
            <div class="sm:col-span-2">
                <label class="gym-label" for="order-search">Mã đơn, tên, số điện thoại hoặc email</label>
                <x-form-input id="order-search" name="q" value="{{ $filters['q'] ?? '' }}" autocomplete="off" placeholder="Ví dụ: 125 hoặc 0901234567" />
            </div>
            <div>
                <label class="gym-label" for="from-date">Từ ngày tạo</label>
                <x-form-input id="from-date" type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" />
            </div>
            <div>
                <label class="gym-label" for="to-date">Đến ngày</label>
                <x-form-input id="to-date" type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" />
            </div>
            <div class="flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-4">
                <button class="gym-btn-primary" type="submit">Tìm đơn</button>
                <a class="font-semibold text-gym-blue underline underline-offset-4" href="{{ route('staff.orders.index', ['status' => $filters['status']]) }}">Xóa bộ lọc</a>
                <span class="text-sm text-slate-600">{{ number_format($orders->total()) }} đơn phù hợp · 20 đơn mỗi trang</span>
            </div>
        </form>

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
                                    <div><label class="gym-label" for="amount-{{ $order->id }}">Số tiền nhận</label><x-form-input id="amount-{{ $order->id }}" type="number" name="amount" min="1" max="{{ $remaining }}" value="{{ $remaining }}" required /></div>
                                    <div><label class="gym-label" for="method-{{ $order->id }}">Hình thức</label><x-form-select id="method-{{ $order->id }}" name="payment_method"><option value="bank_transfer">Chuyển khoản</option><option value="cash">Tiền mặt</option></x-form-select></div>
                                    <div class="sm:col-span-2"><label class="gym-label" for="reference-{{ $order->id }}">Mã giao dịch (nếu có)</label><x-form-input id="reference-{{ $order->id }}" name="reference" maxlength="255" /></div>
                                    <button class="gym-btn-outline sm:col-span-2 sm:justify-self-start" type="submit">Ghi nhận tiền đã nhận</button>
                                </form>
                            @endif
                            <div class="space-y-4">
                                @if ($remaining === 0)
                                    <form class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end" method="POST" action="{{ route('staff.orders.confirm', $order) }}">
                                        @csrf
                                        <div><label class="gym-label" for="start-{{ $order->id }}">Ngày bắt đầu thực tế</label><x-form-input id="start-{{ $order->id }}" type="date" name="start_date" autocomplete="off" min="{{ now(config('app.timezone'))->toDateString() }}" value="{{ $order->start_date->toDateString() >= now(config('app.timezone'))->toDateString() ? $order->start_date->toDateString() : '' }}" required /></div>
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
                <div class="gym-card py-10 text-center text-slate-600">{{ $filters['status'] === 'pending' ? 'Không có đơn cần xử lý phù hợp.' : 'Không tìm thấy đơn phù hợp.' }}</div>
            @endforelse
        </div>

        @if ($orders->hasPages())
            <nav class="mt-6" aria-label="Các trang đơn hội viên">{{ $orders->links() }}</nav>
        @endif
    </section>
@endsection

