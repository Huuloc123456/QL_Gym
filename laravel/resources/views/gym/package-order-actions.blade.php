@php
    $onlinePayments = $order->onlinePayments;
    $received = (int) $order->payments->sum('amount') + (int) $onlinePayments->where('status', 'succeeded')->sum('amount');
    $remaining = max(0, (int) $order->amount - $received);
@endphp

<section class="space-y-4" aria-label="Chi tiết đơn gói hội viên {{ $order->id }}">
    <div>
        <h2 class="font-bold">Đơn gói #{{ $order->id }} · {{ $order->gymPackage->name }}</h2>
        <p class="mt-1 text-sm text-slate-600">{{ $order->user->name }} · {{ $order->user->phone }} · Bắt đầu dự kiến {{ $order->start_date->format('d/m/Y') }}</p>
        @if ($order->duration_months !== null)
            <p class="mt-1 text-sm text-slate-600">Thời hạn đã đặt: {{ $order->duration_months }} tháng</p>
        @endif
    </div>

    <dl class="grid gap-2 border-y border-slate-100 py-3 text-sm sm:grid-cols-3">
        <div><dt class="text-slate-500">Tổng đơn</dt><dd class="font-bold">{{ number_format($order->amount, 0, ',', '.') }} VNĐ</dd></div>
        <div><dt class="text-slate-500">Đã nhận</dt><dd class="font-bold">{{ number_format($received, 0, ',', '.') }} VNĐ</dd></div>
        <div><dt class="text-slate-500">Còn phải thu</dt><dd class="font-bold">{{ number_format($remaining, 0, ',', '.') }} VNĐ</dd></div>
    </dl>

    @if ($order->cancellation_requested_at)
        <p class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">Khách đã gửi yêu cầu hủy lúc {{ $order->cancellation_requested_at->format('d/m/Y H:i') }}.</p>
    @endif

    @if ($order->payments->isNotEmpty() || $onlinePayments->isNotEmpty())
        <div>
            <h3 class="font-semibold">Lịch sử nhận tiền</h3>
            <ul class="mt-2 space-y-1 text-sm text-slate-600">
                @foreach ($order->payments as $payment)
                    <li>{{ $payment->received_at->format('d/m/Y H:i') }} · {{ number_format($payment->amount, 0, ',', '.') }} VNĐ · {{ $payment->payment_method === 'cash' ? 'Tiền mặt' : 'Chuyển khoản' }} · {{ $payment->receiver?->name ?? 'Nhân viên' }} @if ($payment->reference) · {{ $payment->reference }} @endif</li>
                @endforeach
                @foreach ($onlinePayments as $payment)
                    <li>{{ $payment->paid_at ? \Illuminate\Support\Carbon::parse($payment->paid_at)->format('d/m/Y H:i') : \Illuminate\Support\Carbon::parse($payment->created_at)->format('d/m/Y H:i') }} · {{ number_format($payment->amount, 0, ',', '.') }} VNĐ · VNPay · {{ ['pending' => 'Đang chờ cổng thanh toán', 'succeeded' => 'Đã xác nhận', 'failed' => 'Không thành công', 'review' => 'Chờ nhân viên đối soát'][$payment->status] ?? $payment->status }} · Mã {{ $payment->provider_transaction_no ?: $payment->txn_ref }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($order->status === 'pending')
        @if ($remaining > 0)
            <form class="grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2" method="POST" action="{{ route('staff.orders.payments.store', $order) }}">
                @csrf
                <div><label class="gym-label" for="package-order-amount-{{ $order->id }}">Số tiền nhận</label><x-form-input id="package-order-amount-{{ $order->id }}" type="number" name="amount" min="1" max="{{ $remaining }}" value="{{ $remaining }}" required /></div>
                <div><label class="gym-label" for="package-order-method-{{ $order->id }}">Hình thức</label><x-form-select id="package-order-method-{{ $order->id }}" name="payment_method"><option value="bank_transfer" @selected($order->payment_method !== 'cash')>Chuyển khoản</option><option value="cash" @selected($order->payment_method === 'cash')>Tiền mặt</option></x-form-select></div>
                <div class="sm:col-span-2"><label class="gym-label" for="package-order-reference-{{ $order->id }}">Mã giao dịch (nếu có)</label><x-form-input id="package-order-reference-{{ $order->id }}" name="reference" maxlength="255" /></div>
                <button class="gym-btn-outline sm:justify-self-start" type="submit">Ghi nhận tiền đã nhận</button>
            </form>
        @endif

        @if ($remaining === 0)
            <form class="grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-[1fr_auto] sm:items-end" method="POST" action="{{ route('staff.orders.confirm', $order) }}">
                @csrf
                @if ($order->duration_months === null)
                    <div class="sm:col-span-2">
                        <label class="gym-label" for="package-order-duration-{{ $order->id }}">Thời hạn đã thỏa thuận (tháng)</label>
                        <x-form-input id="package-order-duration-{{ $order->id }}" name="duration_months" type="number" min="1" max="65535" required />
                    </div>
                @endif
                <div><label class="gym-label" for="package-order-start-{{ $order->id }}">Ngày bắt đầu thực tế</label><x-form-input id="package-order-start-{{ $order->id }}" type="date" name="start_date" autocomplete="off" min="{{ now(config('app.timezone'))->toDateString() }}" value="{{ $order->start_date->toDateString() >= now(config('app.timezone'))->toDateString() ? $order->start_date->toDateString() : '' }}" required /></div>
                <button class="gym-btn-primary" type="submit">Xác nhận gói</button>
            </form>
        @elseif ($received === 0)
            <form class="border-t border-slate-100 pt-3" method="POST" action="{{ route('staff.orders.cancel', $order) }}" onsubmit="return confirm('Hủy đơn đăng ký này?')">
                @csrf
                <button class="text-sm font-semibold text-red-700 underline underline-offset-4" type="submit">Hủy đơn chưa nhận tiền</button>
            </form>
        @endif
    @elseif ($order->membership)
        <p class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-950">Gói đã hiệu lực từ {{ $order->membership->starts_on->format('d/m/Y') }} đến hết {{ $order->membership->expires_on->copy()->subDay()->format('d/m/Y') }}.</p>
    @elseif ($order->status === 'cancelled')
        <p class="rounded-lg bg-slate-100 p-3 text-sm text-slate-700">Đơn đã hủy.</p>
    @endif
</section>
