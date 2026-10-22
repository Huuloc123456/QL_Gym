<section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="invoice-bank-transfer-qr-title">
    <div class="grid items-center gap-5 p-5 sm:grid-cols-[22rem_1fr] sm:p-6">
        <div class="mx-auto rounded-2xl border border-slate-200 bg-slate-50 p-3">
            <img class="aspect-square w-72 max-w-full rounded-xl bg-white object-contain sm:w-80" src="{{ $qrUrl }}" alt="Mã VietQR thanh toán hóa đơn {{ $invoiceId }}" width="360" height="360" loading="lazy" referrerpolicy="no-referrer">
        </div>
        <div>
            <p class="text-xs font-bold tracking-[0.18em] text-sky-700">THANH TOÁN VIETQR</p>
            <h2 class="mt-1 text-xl font-bold text-slate-900" id="invoice-bank-transfer-qr-title">Quét mã để chuyển khoản</h2>
            @if (($fulfillment?->payment_method ?? null) === 'cash')
                <p class="mt-1 text-sm text-slate-600">Bạn cũng có thể dùng mã này nếu muốn chuyển khoản thay cho tiền mặt.</p>
            @endif
            <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Số tiền</dt><dd class="mt-1 text-lg font-bold text-slate-900">{{ number_format($remainingAmount, 0, ',', '.') }} VNĐ</dd></div>
                <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Nội dung chuyển khoản</dt><dd class="mt-1 font-bold text-slate-900">GYM HD{{ $invoiceId }}</dd></div>
            </dl>
            <p class="mt-3 text-xs leading-5 text-slate-500">Hóa đơn được xác nhận sau khi nhân viên đối chiếu khoản tiền đã nhận.</p>
        </div>
    </div>
</section>
