@extends('layouts.app')
@section('title', 'Hóa đơn #'.$invoice->MAHD)
@section('content')
<section class="gym-container py-10"><header class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-sm text-gym-blue">Đơn hàng và hóa đơn</p><h1 class="gym-title mt-2">Hóa đơn #{{ $invoice->MAHD }}</h1><p class="mt-2 text-slate-500">{{ $invoice->TENKH }} · {{ \Carbon\Carbon::parse($invoice->NGAYLAP)->format('d/m/Y H:i') }}</p></div><strong class="rounded-full bg-sky-50 px-4 py-2 text-gym-blue">{{ $invoice->TRANGTHAI }}</strong></header>
@if (auth()->user()->canManageOrders())<div class="mt-4 flex flex-wrap items-center gap-3"><p class="text-sm text-slate-600">Hệ thống kiểm tra trạng thái mới nhất trước khi ghi nhận tiền, chốt hoặc giao hàng.</p><a class="gym-btn-outline" href="{{ route('gym.invoices.show', $invoice->MAHD) }}">Tải lại hóa đơn</a></div>@endif
@if ($legacyPackageOrder)
<p class="mt-4 text-sm text-slate-600">Hóa đơn này được lập sau khi xác nhận đơn gói #{{ $legacyPackageOrder->id }}. <a class="font-semibold text-gym-blue underline" href="{{ auth()->user()->role === 'customer' ? route('my.orders.show', $legacyPackageOrder) : route('gym.invoices.index', ['source' => 'package_order', 'q' => $legacyPackageOrder->id]) }}">Xem đăng ký và lịch sử thanh toán</a></p>
@endif
@if ($fulfillment?->expires_at && $invoice->TRANGTHAI === 'Chưa thanh toán')<div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-7 text-amber-950"><strong>Hạn thanh toán: {{ \Carbon\Carbon::parse($fulfillment->expires_at)->format('H:i d/m/Y') }}</strong><p>{{ $received > 0 ? 'Đơn đã nhận tiền, cần nhân viên đối chiếu nếu quá hạn; không tự hủy.' : 'Quá hạn chưa nhận tiền, đơn sẽ tự hủy và giải phóng hàng/chỗ. Nếu đã chuyển khoản, liên hệ quầy để đối chiếu ngay.' }}</p></div>@elseif ($fulfillment?->state === 'expired')<p class="mt-6 rounded-xl bg-slate-100 p-4 text-sm">Đơn đã hết hạn thanh toán, hàng/chỗ đã được giải phóng. Bạn có thể đặt đơn mới.</p>@endif
@if ($hasUnsupportedServices && $invoice->TRANGTHAI === 'Chưa thanh toán')<div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-7 text-amber-950"><strong>Hóa đơn này có dịch vụ cũ đã ngừng hỗ trợ.</strong><p>Website không nhận thanh toán mới cho hóa đơn này. Vui lòng liên hệ quản lý để đối chiếu; nếu chưa thanh toán, bạn có thể gửi yêu cầu hủy.</p></div>@endif
@if ($needsReconciliation)<p class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-7 text-amber-950">Hóa đơn đang cần đối chiếu nội dung và số tiền. Vui lòng liên hệ quầy trước khi thanh toán; chức năng thu tiền, chốt và ký được tạm khóa.</p>@endif<div class="mt-7 overflow-x-auto rounded-xl border border-slate-200 bg-white" tabindex="0" aria-label="Chi tiết hóa đơn"><table class="gym-table"><thead><tr><th>Nội dung</th><th>Số lượng/buổi</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead><tbody>@if ($lines->count()) @foreach ($lines as $line)<tr><td>{{ $line->name }} @if ($line->kind === 'package')<p class="text-xs text-slate-500">{{ json_decode($line->details, true)['months'] }} tháng · Bắt đầu {{ json_decode($line->details, true)['start_date'] }}</p>@endif</td><td>{{ $line->quantity }}</td><td class="whitespace-nowrap">@if ($line->original_unit_price !== null && (int) $line->original_unit_price > (int) $line->unit_price)<span class="mr-1 text-sm text-slate-500 line-through">{{ number_format($line->original_unit_price, 0, ',', '.') }}đ</span><strong>{{ number_format($line->unit_price, 0, ',', '.') }}đ</strong><span class="block text-xs font-semibold text-emerald-700">Giá khuyến mãi</span>@else{{ number_format($line->unit_price, 0, ',', '.') }}đ@endif</td><td class="whitespace-nowrap">{{ number_format($line->quantity * $line->unit_price, 0, ',', '.') }}đ</td></tr>@endforeach @else @foreach ($originalLines as $line)<tr><td>{{ $line->TENHANG }}</td><td>{{ $line->SOLUONG }}</td><td>{{ number_format($line->DONGIA, 0, ',', '.') }}đ</td><td>{{ number_format($line->THANHTIEN_SP, 0, ',', '.') }}đ</td></tr>@endforeach @endif</tbody></table></div>
<div class="mt-7 grid items-start gap-6 lg:grid-cols-2"><div class="gym-card"><h2 class="text-xl font-bold">Thanh toán</h2><dl class="mt-4 space-y-3"><div class="flex justify-between gap-4"><dt>Tiền hàng/dịch vụ</dt><dd>{{ number_format($invoice->TONGTIEN, 0, ',', '.') }}đ</dd></div><div class="flex justify-between gap-4"><dt>Giảm giá</dt><dd>−{{ number_format((int) $invoice->GIAMGIA, 0, ',', '.') }}đ</dd></div><div class="flex justify-between gap-4"><dt>Phí giao hàng</dt><dd>{{ number_format($fulfillment?->shipping_fee ?? 0, 0, ',', '.') }}đ</dd></div><div class="flex justify-between gap-4 border-t pt-3 text-lg font-bold"><dt>Thành tiền</dt><dd>{{ number_format($invoice->THANHTIEN, 0, ',', '.') }}đ</dd></div>@if ($invoice->TRANGTHAI !== 'Đã thanh toán')<div class="flex justify-between gap-4"><dt>Đã nhận</dt><dd>{{ number_format($received, 0, ',', '.') }}đ</dd></div><div class="flex justify-between gap-4"><dt>Còn phải thu</dt><dd>{{ number_format($remainingAmount, 0, ',', '.') }}đ</dd></div>@endif</dl>
@if ((int) $invoice->GIAMGIA > 0 && $needsReconciliation)<p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm leading-6 text-amber-950">Căn cứ giảm giá chưa khớp với hóa đơn và đang được đối soát. Tạm thời chưa thể xác nhận nguồn hoặc tỷ lệ giảm.</p>@elseif ((int) $invoice->GIAMGIA > 0 && $discountSnapshot)<p class="mt-4 rounded-lg bg-sky-50 p-3 text-sm leading-6 text-sky-950">Ưu đãi hạng {{ $discountSnapshot->tier_name }} ({{ $discountSnapshot->rate_percent }}%) trên {{ number_format($discountSnapshot->base_amount, 0, ',', '.') }}đ: giảm {{ number_format($discountSnapshot->discount_amount, 0, ',', '.') }}đ.</p>@elseif ((int) $invoice->GIAMGIA > 0)<p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm leading-6 text-amber-950">Giảm giá theo dữ liệu hóa đơn cũ — chưa xác định nguồn.</p>@endif
<p class="mt-4 text-sm text-slate-500">Ghi đúng mã hóa đơn khi thanh toán và chờ nhân viên đối chiếu.</p></div>
@if ($fulfillment)<div class="gym-card"><h2 class="text-xl font-bold">Thông tin nhận hàng</h2><p class="mt-4 font-semibold">{{ $fulfillment->mode === 'delivery' ? 'Giao theo địa chỉ' : 'Nhận tại quầy The Gym' }}</p><p class="mt-2">{{ $fulfillment->recipient }} · {{ $fulfillment->phone }}</p>@if ($fulfillment->address) @php($address = json_decode($fulfillment->address, true))<p class="mt-2 text-sm leading-7">{{ $address['DIACHICUTHE'] }}, {{ $address['PHUONGXA'] }}, {{ $address['QUANHUYEN'] }}, {{ $address['TINHTHANHPHO'] }}</p>@endif<p class="mt-3 text-sm">Phí giao hàng: {{ number_format($fulfillment->shipping_fee, 0, ',', '.') }}đ</p><p class="mt-3 font-semibold">{{ ['pending' => 'Chờ chuẩn bị', 'ready' => 'Sẵn sàng nhận', 'collected' => 'Đã nhận', 'shipping' => 'Đang giao', 'delivered' => 'Đã giao', 'cancelled' => 'Đã hủy', 'expired' => 'Hết hạn thanh toán'][$fulfillment->state] ?? $fulfillment->state }}</p></div>@endif</div>
@unless($needsReconciliation || $hasUnsupportedServices)<div class="mt-6">@include('gym.payment-details')</div>@endunless
@if ($bankTransferQrUrl)
    @include('gym.invoice-bank-transfer-qr', ['qrUrl' => $bankTransferQrUrl, 'invoiceId' => $invoice->MAHD, 'remainingAmount' => $remainingAmount])
@endif
    @if ($receipts->count() || $onlinePayments->count())<section class="gym-card mt-6"><h2 class="text-xl font-bold">Lịch sử thanh toán</h2><ul class="mt-4 space-y-3 text-sm">@foreach ($receipts as $receipt)<li>{{ $receipt->created_at }} · {{ number_format($receipt->amount, 0, ',', '.') }}đ · {{ $receipt->method === 'cash' ? 'Tiền mặt' : 'Chuyển khoản' }} · {{ $receipt->name }} @if ($receipt->reference) · {{ $receipt->reference }} @endif</li>@endforeach @foreach ($onlinePayments as $payment)<li>{{ $payment->paid_at ?: $payment->created_at }} · {{ number_format($payment->amount, 0, ',', '.') }}đ · VNPay · {{ ['pending' => 'Đang chờ cổng thanh toán', 'succeeded' => 'Đã xác nhận', 'failed' => 'Không thành công', 'review' => 'Chờ nhân viên đối soát'][$payment->status] ?? $payment->status }} · Mã {{ $payment->txn_ref }}@if ($payment->review_reason) · Lý do: {{ $payment->review_reason }}@endif</li>@endforeach</ul></section>@endif
@if ($invoice->TRANGTHAI === 'Chưa thanh toán')
    @if ($hasPaymentReview)
        <p class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-950">Hóa đơn có giao dịch VNPay đang chờ quản lý đối soát. Vui lòng không thu hoặc thanh toán thêm trước khi xử lý xong.</p>
    @elseif ($hasUnsupportedServices)
        <div class="mt-5">@if (auth()->user()->role === 'customer')<form method="POST" action="{{ route('gym.invoices.cancel', $invoice->MAHD) }}">@csrf<button class="gym-btn-outline" type="submit">Gửi yêu cầu hủy</button></form>@elseif (auth()->user()->canManageOrders() && $received === 0)<form method="POST" action="{{ route('gym.invoices.cancel', $invoice->MAHD) }}">@csrf<button class="gym-btn-outline" type="submit">Hủy hóa đơn chưa nhận tiền</button></form>@endif</div>
    @elseif (auth()->user()->canManageOrders())
        @if ($fulfillment?->cancellation_requested)
            <div class="mt-7 space-y-4 rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-950">
                <div>
                    <h2 class="font-bold">Khách đã yêu cầu hủy đơn</h2>
                    <p class="mt-1 text-sm">Trao đổi với khách rồi chọn hủy đơn hoặc xác nhận tiếp tục trước khi ghi nhận tiền.</p>
                </div>
                <form method="POST" action="{{ route('gym.invoices.resume-cancellation', $invoice->MAHD) }}">
                    @csrf
                    <button class="gym-btn-outline" type="submit">Xác nhận tiếp tục đơn</button>
                </form>
                @if ($received === 0)
                    <form method="POST" action="{{ route('gym.invoices.cancel', $invoice->MAHD) }}">
                        @csrf
                        <button class="text-sm font-semibold text-red-700 underline" type="submit">Hủy hóa đơn và giải phóng hàng</button>
                    </form>
                @else
                    <p class="text-sm">Đơn đã nhận tiền. Cần hoàn tiền và ghi nhận chứng từ trước khi có thể hủy.</p>
                @endif
            </div>
        @else
            <div class="mt-7 grid items-start gap-6 lg:grid-cols-2">
                <form class="gym-card space-y-4" method="POST" action="{{ route('gym.invoices.receive', $invoice->MAHD) }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <h2 class="text-xl font-bold">Ghi nhận khoản thu</h2>
                    <div><label class="gym-label" for="amount">Số tiền</label><input class="gym-input" id="amount" name="amount" type="number" min="1" max="{{ max(0, $invoice->THANHTIEN - $received) }}" required></div>
                    <div><label class="gym-label" for="method">Hình thức</label><select class="gym-input" id="method" name="method"><option value="cash">Tiền mặt</option><option value="bank_transfer">Chuyển khoản</option></select></div>
                    <div><label class="gym-label" for="reference">Mã tham chiếu/biên nhận</label><input class="gym-input" id="reference" name="reference" maxlength="100"></div>
                    <button class="gym-btn-primary" type="submit" @disabled($needsReconciliation)>Ghi nhận tiền</button>
                </form>
                <div class="gym-card space-y-5">
                    <form class="space-y-4" method="POST" action="{{ route('gym.invoices.confirm', $invoice->MAHD) }}">
                        @csrf
                        <h2 class="text-xl font-bold">Chốt hóa đơn</h2>
                        @if ($lines->contains('kind', 'package'))
                            <div><label class="gym-label" for="start_date">Ngày bắt đầu đã chốt với khách</label><input class="gym-input" id="start_date" name="start_date" type="date" min="{{ now()->toDateString() }}"><p class="mt-1 text-xs text-slate-500">Để trống để giữ ngày trên đơn; nếu ngày đã qua cần chốt ngày mới.</p></div>
                        @endif
                        <button class="gym-btn-primary" type="submit" @disabled($needsReconciliation)>Xác nhận đã thu đủ tiền</button>
                    </form>
                    <form method="POST" action="{{ route('gym.invoices.cancel', $invoice->MAHD) }}">
                        @csrf
                        <button class="text-sm font-semibold text-red-700 underline" type="submit">Hủy hóa đơn chưa nhận tiền</button>
                    </form>
                </div>
            </div>
        @endif
    @elseif ($fulfillment)<div class="mt-5 flex flex-wrap items-center gap-4">@if ($fulfillment->payment_method === 'vnpay' && ! $needsReconciliation && $received < (int) $invoice->THANHTIEN)@if (config('vnpay.tmn_code') && config('vnpay.hash_secret'))<form method="POST" action="{{ route('gym.invoices.vnpay', $invoice->MAHD) }}">@csrf<button class="gym-btn-primary" type="submit">Thanh toán online qua VNPay · {{ number_format((int) $invoice->THANHTIEN - $received, 0, ',', '.') }}đ</button></form>@else<p class="text-sm font-semibold text-amber-800">Thanh toán online chưa được cấu hình; vui lòng liên hệ quầy để thanh toán.</p>@endif @endif<form method="POST" action="{{ route('gym.invoices.cancel', $invoice->MAHD) }}">@csrf<button class="gym-btn-outline" type="submit">Gửi yêu cầu hủy</button></form></div>@endif
@endif
@if ($refunds->isNotEmpty())
    <section class="gym-card mt-6"><h2 class="text-xl font-bold">Lịch sử hoàn tiền</h2><ul class="mt-4 space-y-2 text-sm">@foreach ($refunds as $refund)<li>{{ \Illuminate\Support\Carbon::parse($refund->refunded_at)->format('d/m/Y H:i') }} · {{ number_format($refund->amount, 0, ',', '.') }} VNĐ · Chứng từ {{ $refund->reference }} · {{ $refund->reason }}</li>@endforeach</ul></section>
@endif
@if (auth()->user()->role === 'manager' && $invoice->TRANGTHAI === 'Chưa thanh toán' && $receipts->isNotEmpty())
    <section class="gym-card mt-6 space-y-4"><div><h2 class="text-xl font-bold">Ghi nhận hoàn tiền</h2><p class="mt-2 text-sm text-slate-600">Hoàn tiền cho khách bên ngoài website trước, rồi ghi lại chứng từ. Không xóa khoản thu ban đầu.</p></div>
        @foreach ($receipts as $receipt)
            @php($refundable = (int) $receipt->amount - (int) $refunds->where('source_type', $receipt->source_type)->where('source_id', $receipt->id)->sum('amount'))
            @if ($refundable > 0)
                <form class="grid gap-4 border-t border-slate-200 pt-4 sm:grid-cols-2" method="POST" action="{{ route('manager.refunds.store') }}">
                    @csrf<input type="hidden" name="source_type" value="{{ $receipt->source_type }}"><input type="hidden" name="source_id" value="{{ $receipt->id }}"><input type="hidden" name="token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <p class="text-sm sm:col-span-2">Khoản thu #{{ $receipt->id }} · Còn có thể hoàn {{ number_format($refundable, 0, ',', '.') }} VNĐ</p>
                    <div><label class="gym-label" for="refund-amount-{{ $receipt->id }}">Số tiền đã hoàn</label><input class="gym-input" id="refund-amount-{{ $receipt->id }}" name="amount" type="number" min="1" max="{{ $refundable }}" value="{{ $refundable }}" required></div>
                    <div><label class="gym-label" for="refund-reference-{{ $receipt->id }}">Mã chứng từ hoàn</label><input class="gym-input" id="refund-reference-{{ $receipt->id }}" name="reference" minlength="3" maxlength="100" required></div>
                    <div class="sm:col-span-2"><label class="gym-label" for="refund-reason-{{ $receipt->id }}">Lý do hoàn</label><input class="gym-input" id="refund-reason-{{ $receipt->id }}" name="reason" minlength="5" maxlength="1000" required></div>
                    <button class="gym-btn-outline sm:justify-self-start" type="submit">Ghi nhận hoàn tiền</button>
                </form>
            @endif
        @endforeach
    </section>
@endif
@if (auth()->user()->canManageOrders() && $fulfillment && $invoice->TRANGTHAI === 'Đã thanh toán' && $lines->contains('kind', 'product'))
@php($nextState = $fulfillment->mode === 'delivery' ? ['pending' => 'shipping', 'shipping' => 'delivered'] : ['pending' => 'ready', 'ready' => 'collected'])
@if (isset($nextState[$fulfillment->state]))<form class="mt-6" method="POST" action="{{ route('gym.invoices.fulfill', $invoice->MAHD) }}">@csrf<input type="hidden" name="state" value="{{ $nextState[$fulfillment->state] }}"><button class="gym-btn-primary" type="submit">{{ ['ready' => 'Đánh dấu sẵn sàng nhận', 'collected' => 'Xác nhận khách đã nhận', 'shipping' => 'Bắt đầu giao hàng', 'delivered' => 'Xác nhận đã giao'][$nextState[$fulfillment->state]] }}</button></form>@endif @endif
@if ($invoice->TRANGTHAI === 'Đã thanh toán')<section class="gym-card mt-7"><h2 class="text-xl font-bold">Chứng từ và chữ ký</h2><p class="mt-3 text-sm text-slate-500">Tải chứng từ HTML để lưu/in PDF từ trình duyệt. Kiểm tra chữ ký để phát hiện nội dung bị sửa.</p><div class="mt-4 flex flex-wrap gap-3">@if (auth()->user()->role === 'manager')<form method="POST" action="{{ route('gym.invoices.sign', $invoice->MAHD) }}">@csrf<button class="gym-btn-primary" type="submit" @disabled($needsReconciliation || $hasPaymentReview)>Tạo chứng từ đã ký</button></form>@endif @if ($invoice->FILE_PATH && str_starts_with($invoice->FILE_PATH, 'gym-invoices/'))<a class="gym-btn-outline" href="{{ route('gym.invoices.download', $invoice->MAHD) }}">Tải chứng từ</a><form method="POST" action="{{ route('gym.invoices.verify', $invoice->MAHD) }}">@csrf<button class="gym-btn-outline" type="submit">Kiểm tra tệp gốc</button></form>@endif</div>@if ($invoice->SIGNATURE_PATH && str_starts_with($invoice->SIGNATURE_PATH, 'gym-invoices/'))<form class="mt-5 flex flex-wrap items-end gap-3" method="POST" enctype="multipart/form-data" action="{{ route('gym.invoices.verify', $invoice->MAHD) }}">@csrf<div><label class="gym-label" for="document">Kiểm tra bản tệp bạn đang giữ</label><input class="gym-input" id="document" type="file" name="document" required></div><button class="gym-btn-outline" type="submit">Kiểm tra tệp tải lên</button></form><p class="mt-4 text-sm">Lần kiểm tra tệp gốc: {{ $invoice->SIGNATURE_VERIFIED ? 'Hợp lệ' : 'Chưa hợp lệ' }} · {{ $invoice->VERIFIED_AT }}</p>@endif</section>@endif
</section>
@endsection
