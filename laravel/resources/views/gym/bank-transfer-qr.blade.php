<section class="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sky-950" aria-labelledby="bank-transfer-title-{{ $order->id }}">
    <h2 class="font-bold" id="bank-transfer-title-{{ $order->id }}">Thanh toán bằng mã QR</h2>
    @if ($remainingAmount < 1)
        <p class="mt-2 text-sm leading-6">Phòng tập đã ghi nhận đủ số tiền. Đơn đang chờ nhân viên xác nhận.</p>
    @elseif ($qrUrl)
        <div class="mt-3 grid items-center gap-4 sm:grid-cols-[11rem_1fr]">
            <img class="mx-auto aspect-square w-44 rounded-lg border border-slate-200 bg-white object-contain p-2" src="{{ $qrUrl }}" alt="Mã QR chuyển khoản cho đơn {{ $order->id }}" width="220" height="220" loading="lazy" referrerpolicy="no-referrer">
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                @if ($gymSettings->bank_name)<div><dt class="text-sky-800">Ngân hàng</dt><dd class="font-bold">{{ $gymSettings->bank_name }}</dd></div>@endif
                <div><dt class="text-sky-800">Số tài khoản</dt><dd class="font-bold">{{ $gymSettings->bank_account }}</dd></div>
                <div><dt class="text-sky-800">Chủ tài khoản</dt><dd class="font-bold">{{ $gymSettings->bank_account_name }}</dd></div>
                <div><dt class="text-sky-800">Số tiền cần chuyển</dt><dd class="font-bold">{{ number_format($remainingAmount, 0, ',', '.') }} VNĐ</dd></div>
                <div class="sm:col-span-2"><dt class="text-sky-800">Nội dung chuyển khoản</dt><dd class="font-bold">GYM-{{ $order->id }}</dd></div>
            </dl>
        </div>
        <p class="mt-3 text-sm leading-6">Quét mã bằng ứng dụng ngân hàng. Mã đã điền số tiền và nội dung đơn. Gói chỉ được kích hoạt sau khi nhân viên xác nhận đã nhận tiền.</p>
    @else
        @if ($gymSettings?->bank_account && $gymSettings?->bank_account_name)
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                @if ($gymSettings->bank_name)<div><dt class="text-sky-800">Ngân hàng</dt><dd class="font-bold">{{ $gymSettings->bank_name }}</dd></div>@endif
                <div><dt class="text-sky-800">Số tài khoản</dt><dd class="font-bold">{{ $gymSettings->bank_account }}</dd></div>
                <div><dt class="text-sky-800">Chủ tài khoản</dt><dd class="font-bold">{{ $gymSettings->bank_account_name }}</dd></div>
                <div><dt class="text-sky-800">Số tiền cần chuyển</dt><dd class="font-bold">{{ number_format($remainingAmount, 0, ',', '.') }} VNĐ</dd></div>
                <div class="sm:col-span-2"><dt class="text-sky-800">Nội dung chuyển khoản</dt><dd class="font-bold">GYM-{{ $order->id }}</dd></div>
            </dl>
            <p class="mt-3 text-sm leading-6">Chưa có mã VietQR. Bạn có thể chuyển khoản theo thông tin trên hoặc liên hệ quầy. Nhân viên sẽ xác nhận khoản đã nhận trước khi kích hoạt gói.</p>
        @else
            <p class="mt-2 text-sm leading-6">Phòng tập chưa cấu hình đầy đủ mã VietQR và tài khoản nhận tiền. Vui lòng liên hệ quầy để được hướng dẫn; nhân viên sẽ xác nhận khoản đã nhận trước khi kích hoạt gói.</p>
        @endif
    @endif
</section>
