@extends('layouts.app')

@section('title', 'Đối soát thanh toán VNPay')

@section('content')
    <section class="gym-container space-y-6 py-10">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-gym-blue">Khu vực quản lý</p>
                <h1 class="gym-title mt-2">Đối soát thanh toán VNPay</h1>
                <p class="mt-2 max-w-3xl text-slate-600">Các giao dịch thành công nhưng chưa thể tự động chốt đơn sẽ nằm ở đây. Đối chiếu với giao dịch ngân hàng trước khi xác nhận hoặc ghi nhận hoàn tiền.</p>
            </div>
            <a class="gym-btn-outline" href="{{ route('gym.invoices.index') }}">Mở danh sách đơn</a>
        </header>

        @forelse ($payments as $payment)
            <article class="gym-card space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold">{{ $payment->order_label }}</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $payment->customer_name }} · {{ number_format((int) $payment->amount, 0, ',', '.') }} VNĐ · {{ \Illuminate\Support\Carbon::parse($payment->created_at)->format('d/m/Y H:i') }}</p>
                        <p class="mt-1 text-sm font-semibold text-amber-800">Mã giao dịch: {{ $payment->txn_ref }}</p>
                        @if ($payment->provider_transaction_no)<p class="mt-1 text-sm text-slate-600">Mã giao dịch ngân hàng: {{ $payment->provider_transaction_no }}</p>@endif
                        <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950">{{ $payment->review_reason ?: 'Cần kiểm tra lại giao dịch và đơn hàng.' }}</p>
                    </div>
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-900">Chờ đối soát</span>
                </div>

                <div class="grid gap-4 border-t border-slate-200 pt-4 lg:grid-cols-2">
                    <form class="space-y-3" method="POST" action="{{ route('manager.payment-reviews.confirm', $payment->id) }}">
                        @csrf
                        <h3 class="font-bold">Xác nhận đã nhận tiền</h3>
                        <label class="gym-label" for="confirm-reference-{{ $payment->id }}">Mã chứng từ đối chiếu (nếu có)</label>
                        <input class="gym-input" id="confirm-reference-{{ $payment->id }}" name="reference" maxlength="100" value="{{ $payment->provider_transaction_no }}">
                        <label class="gym-label" for="confirm-date-{{ $payment->id }}">Ngày bắt đầu gói mới (nếu cần)</label>
                        <input class="gym-input" id="confirm-date-{{ $payment->id }}" name="start_date" type="date" min="{{ now()->toDateString() }}">
                        <label class="gym-label" for="confirm-note-{{ $payment->id }}">Ghi chú kiểm tra</label>
                        <textarea class="gym-input min-h-20" id="confirm-note-{{ $payment->id }}" name="note" minlength="8" maxlength="1000" required></textarea>
                        <button class="gym-btn-primary" type="submit">Xác nhận và chốt đơn</button>
                    </form>

                    <form class="space-y-3" method="POST" action="{{ route('manager.payment-reviews.refund', $payment->id) }}">
                        @csrf
                        <h3 class="font-bold">Ghi nhận đã hoàn tiền</h3>
                        <p class="text-sm text-slate-600">Thực hiện hoàn tiền qua ngân hàng trước, sau đó nhập mã chứng từ để lưu lại. Website không tự chuyển tiền hoàn.</p>
                        <label class="gym-label" for="refund-reference-{{ $payment->id }}">Mã chứng từ hoàn tiền</label>
                        <input class="gym-input" id="refund-reference-{{ $payment->id }}" name="reference" minlength="3" maxlength="100" required>
                        <label class="gym-label" for="refund-note-{{ $payment->id }}">Ghi chú hoàn tiền</label>
                        <textarea class="gym-input min-h-20" id="refund-note-{{ $payment->id }}" name="note" minlength="8" maxlength="1000" required></textarea>
                        <button class="gym-btn-outline" type="submit">Lưu hoàn tiền đã thực hiện</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="gym-card py-10 text-center">
                <h2 class="text-lg font-bold">Không có giao dịch chờ đối soát</h2>
                <p class="mt-2 text-slate-600">Giao dịch cần kiểm tra sẽ xuất hiện ở đây.</p>
            </div>
        @endforelse
    </section>
@endsection
