@extends('layouts.app')
@section('title', 'Chính sách và quy định sử dụng')
@section('content')
<section class="gym-container py-12"><div class="mx-auto max-w-3xl"><h1 class="gym-title">Chính sách và quy định sử dụng</h1><div class="mt-7 grid gap-5">
@foreach ([['Đặt gói và sản phẩm', 'Giá, ưu đãi, thời hạn và phí giao được chốt tại thời điểm đặt và hiển thị trên hóa đơn. Nhận tại quầy không mất phí. Sản phẩm chỉ được giao hoặc bàn giao sau khi hóa đơn đã thanh toán.'], ['Thanh toán và kích hoạt gói', 'Đơn mới có hạn thanh toán '.$settings->payment_hours.' giờ hoặc thời hạn cụ thể ghi trên hóa đơn. VNPay chỉ được xác nhận sau IPN hợp lệ. Tiền mặt và chuyển khoản thủ công cần nhân viên đối chiếu. Gói tập chỉ có hiệu lực sau khi thanh toán được xác nhận.'], ['Hủy đơn và hoàn tiền', 'Khách có thể gửi yêu cầu hủy đơn đang chờ thanh toán. Nhân viên xử lý nếu chưa nhận tiền. Đơn đã nhận tiền hoặc đã thanh toán cần được đối chiếu tại quầy; website lưu chứng từ hoàn tiền nhưng không tự chuyển tiền về ngân hàng.'], ['Nhận hàng và giao hàng', 'Phí giao hiện hành là '.number_format($settings->shipping_fee, 0, ',', '.').'đ/đơn có sản phẩm và được hiển thị trước khi đặt. Kiểm tra tên, số điện thoại, địa chỉ nhận. Nhân viên cập nhật trạng thái chuẩn bị, giao hàng hoặc nhận tại quầy.'], ['Thông tin và bảo mật tài khoản', 'Giữ kín mật khẩu, mã OTP và liên kết khôi phục. Tài khoản chỉ xem hồ sơ, thông báo, gói và đơn hàng thuộc về mình. Liên hệ phòng tập nếu cần cập nhật dữ liệu tài khoản.']] as [$title, $body])
    <section class="gym-card"><h2 class="text-xl font-bold">{{ $title }}</h2><p class="mt-4 leading-7 text-slate-600">{{ $body }}</p></section>
@endforeach
</div><a class="gym-btn-outline mt-6" href="{{ route('gym.contact') }}">Liên hệ phòng gym</a></div></section>
@endsection
