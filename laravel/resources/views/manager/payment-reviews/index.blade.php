@extends('layouts.app')
@section('title', 'Đối soát thanh toán')
@section('content')
<section class="gym-container py-10">
    <h1 class="gym-title">Đối soát thanh toán</h1>
    <p class="mt-3 text-sm leading-7 text-slate-600">Các giao dịch cổng báo thành công nhưng chưa hoàn tất đơn. Đối chiếu ngân hàng và trao đổi với khách trước khi ghi nhận. Hoàn tiền phải thực hiện bên ngoài cổng rồi mới lưu chứng từ tại đây.</p>
    <div class="mt-6 grid gap-5">
        @forelse ($payments as $payment)
            <article class="gym-card space-y-4">
                <h2 class="text-lg font-bold">{{ $payment->customer_name }} · {{ number_format($payment->amount, 0, ',', '.') }}đ</h2>
                <p class="text-sm">Mã {{ $payment->txn_ref }} · {{ $payment->review_reason }}</p>
                <a class="text-gym-blue underline" href="{{ $payment->target_type === 'invoice' ? route('gym.invoices.show', $payment->target_id) : route('staff.orders.index', ['q' => $payment->target_id]) }}">Mở đơn liên quan</a>
                <form method="POST" action="{{ route('manager.payment-reviews.resolve', $payment->id) }}" class="grid gap-4 sm:grid-cols-2">
                    @csrf
                    <div><label class="gym-label" for="action-{{ $payment->id }}">Kết quả</label><select class="gym-input" id="action-{{ $payment->id }}" name="action"><option value="confirmed">Ghi nhận tiền và tiếp tục đơn</option><option value="refunded">Đã hoàn tiền bên ngoài hệ thống</option></select></div>
                    <div><label class="gym-label" for="reference-{{ $payment->id }}">Mã chứng từ (bắt buộc nếu đã hoàn)</label><input class="gym-input" id="reference-{{ $payment->id }}" name="reference" maxlength="100"></div>
                    <div><label class="gym-label" for="start-{{ $payment->id }}">Ngày bắt đầu gói nếu cần chốt lại</label><input class="gym-input" type="date" id="start-{{ $payment->id }}" name="start_date" min="{{ now()->toDateString() }}"></div>
                    <div><label class="gym-label" for="note-{{ $payment->id }}">Ghi chú đối chiếu (ít nhất 8 ký tự)</label><textarea class="gym-input" id="note-{{ $payment->id }}" name="note" required minlength="8" maxlength="1000"></textarea></div>
                    <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="verified" value="1" required> Đã đối chiếu ngân hàng và xác nhận với khách về kết quả xử lý</label>
                    <button class="gym-btn-primary" type="submit">Lưu kết quả đối soát</button>
                </form>
            </article>
        @empty
            <p class="gym-card text-slate-500">Không có giao dịch chờ đối soát.</p>
        @endforelse
    </div>
    <div class="mt-5">{{ $payments->links() }}</div>
</section>
@endsection
