@extends('layouts.app')

@section('title', 'Trạng thái thanh toán VNPay')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <article class="gym-card mx-auto max-w-2xl p-6 sm:p-9">
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-gym-blue">Thanh toán VNPay</p>
            @if ($payment->status === 'succeeded')
                <h1 class="gym-title mt-3">Thanh toán đã được xác nhận</h1>
                <p class="mt-3 text-slate-600">Máy chủ đã nhận IPN hợp lệ từ VNPay và cập nhật đơn hàng.</p>
            @elseif ($payment->status === 'review')
                <h1 class="gym-title mt-3">Giao dịch cần được đối soát</h1>
                <p class="mt-3 text-slate-600">Cổng thanh toán báo giao dịch thành công nhưng đơn không thể tự hoàn tất. Vui lòng liên hệ quầy và cung cấp mã giao dịch.</p>
            @elseif ($payment->status === 'failed')
                <h1 class="gym-title mt-3">Giao dịch chưa thành công</h1>
                <p class="mt-3 text-slate-600">Đơn chưa được xác nhận thanh toán. Bạn có thể thử lại trên trang đơn hàng.</p>
            @else
                <h1 class="gym-title mt-3">Đang chờ xác nhận từ VNPay</h1>
                <p class="mt-3 text-slate-600">Trang quay về chỉ hiển thị trạng thái. Hệ thống chỉ xác nhận sau khi nhận IPN hợp lệ từ VNPay.</p>
            @endif
            <dl class="mt-6 grid gap-3 border-y border-slate-100 py-5 sm:grid-cols-2">
                <div><dt class="text-sm text-slate-500">Mã giao dịch</dt><dd class="mt-1 font-semibold">{{ $payment->txn_ref }}</dd></div>
                <div><dt class="text-sm text-slate-500">Số tiền</dt><dd class="mt-1 font-semibold">{{ number_format($payment->amount, 0, ',', '.') }} VNĐ</dd></div>
            </dl>
            <a class="gym-btn-primary mt-6" href="{{ $payment->target_type === 'invoice' ? route('gym.invoices.show', $payment->target_id) : route('login') }}">{{ $payment->target_type === 'invoice' ? 'Xem hóa đơn' : 'Đăng nhập để xem đơn' }}</a>
        </article>
    </section>
@endsection
