@extends('layouts.app')

@section('title', 'Cài đặt phòng tập')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-8">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Thiết lập vận hành</p>
            <h1 class="gym-title mt-2">Cài đặt phòng tập</h1>
            <p class="mt-2 max-w-3xl text-slate-600">Cập nhật thông tin hiển thị trên website, hướng dẫn thanh toán và quy tắc cho đơn mới. Đơn đã tạo giữ nguyên điều kiện tại thời điểm đặt.</p>
        </header>

        <form class="gym-card space-y-8 p-6 sm:p-8" method="POST" action="{{ route('manager.settings.update') }}">
            @csrf
            @method('PUT')

            <section aria-labelledby="gym-contact-settings">
                <h2 class="text-lg font-bold" id="gym-contact-settings">Thông tin phòng tập</h2>
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div><label class="gym-label" for="gym_name">Tên phòng tập</label><x-form-input id="gym_name" name="gym_name" value="{{ old('gym_name', $settings->gym_name) }}" maxlength="120" required /></div>
                    <div><label class="gym-label" for="phone">Số điện thoại</label><x-form-input id="phone" name="phone" type="tel" value="{{ old('phone', $settings->phone) }}" maxlength="30" /></div>
                    <div><label class="gym-label" for="email">Email liên hệ</label><x-form-input id="email" name="email" type="email" value="{{ old('email', $settings->email) }}" maxlength="255" /></div>
                    <div><label class="gym-label" for="opening_hours">Giờ mở cửa/nhận hàng</label><x-form-input id="opening_hours" name="opening_hours" value="{{ old('opening_hours', $settings->opening_hours) }}" maxlength="255" placeholder="Ví dụ: 06:00–22:00 mỗi ngày" /></div>
                    <div class="sm:col-span-2"><label class="gym-label" for="address">Địa chỉ</label><x-form-input id="address" name="address" value="{{ old('address', $settings->address) }}" maxlength="500" /></div>
                    <div class="sm:col-span-2"><label class="gym-label" for="introduction">Giới thiệu ngắn</label><x-form-textarea id="introduction" name="introduction" rows="4" maxlength="3000">{{ old('introduction', $settings->introduction) }}</x-form-textarea></div>
                </div>
            </section>

            <section class="border-t border-slate-200 pt-7" aria-labelledby="gym-payment-settings">
                <h2 class="text-lg font-bold" id="gym-payment-settings">Hướng dẫn chuyển khoản</h2>
                <p class="mt-1 text-sm text-slate-600">Nhân viên vẫn cần đối chiếu khoản tiền đã nhận trước khi xác nhận đơn.</p>
                <div class="mt-4 grid gap-5 sm:grid-cols-3">
                    <div><label class="gym-label" for="bank_name">Ngân hàng</label><x-form-input id="bank_name" name="bank_name" value="{{ old('bank_name', $settings->bank_name) }}" maxlength="120" /></div>
                    <div><label class="gym-label" for="bank_account">Số tài khoản</label><x-form-input id="bank_account" name="bank_account" value="{{ old('bank_account', $settings->bank_account) }}" maxlength="80" /></div>
                    <div><label class="gym-label" for="bank_account_name">Chủ tài khoản</label><x-form-input id="bank_account_name" name="bank_account_name" value="{{ old('bank_account_name', $settings->bank_account_name) }}" maxlength="160" /></div>
                </div>
            </section>

            <section class="border-t border-slate-200 pt-7" aria-labelledby="gym-faq-settings">
                <h2 class="text-lg font-bold" id="gym-faq-settings">Câu hỏi thường gặp</h2>
                <p class="mt-1 text-sm text-slate-600">Câu trả lời sẽ hiển thị công khai trên trang chủ. Chỉ ghi nội dung phòng tập đã xác nhận.</p>
                @php($faqItems = old('faq_items', $settings->faq_items ?? []))
                <div class="mt-4 grid gap-4">
                    @foreach (range(0, 3) as $faqIndex)
                        <fieldset class="grid gap-4 rounded-2xl border border-slate-200 p-4 sm:grid-cols-2">
                            <legend class="px-2 text-sm font-semibold text-slate-700">Câu hỏi {{ $faqIndex + 1 }}</legend>
                            <div><label class="gym-label" for="faq-question-{{ $faqIndex }}">Câu hỏi</label><x-form-input id="faq-question-{{ $faqIndex }}" name="faq_items[{{ $faqIndex }}][question]" value="{{ $faqItems[$faqIndex]['question'] ?? '' }}" maxlength="180" /></div>
                            <div><label class="gym-label" for="faq-answer-{{ $faqIndex }}">Câu trả lời</label><x-form-textarea id="faq-answer-{{ $faqIndex }}" name="faq_items[{{ $faqIndex }}][answer]" rows="2" maxlength="800">{{ $faqItems[$faqIndex]['answer'] ?? '' }}</x-form-textarea></div>
                        </fieldset>
                    @endforeach
                </div>
            </section>

            <section class="border-t border-slate-200 pt-7" aria-labelledby="gym-order-rules">
                <h2 class="text-lg font-bold" id="gym-order-rules">Quy tắc đơn hàng và thông báo</h2>
                <div class="mt-4 grid gap-5 sm:grid-cols-3">
                    <div><label class="gym-label" for="payment_hours">Hạn thanh toán đơn mới (giờ)</label><x-form-input id="payment_hours" name="payment_hours" type="number" min="1" max="168" value="{{ old('payment_hours', $settings->payment_hours) }}" required /></div>
                    <div><label class="gym-label" for="reminder_days">Nhắc trước khi hết gói (ngày)</label><x-form-input id="reminder_days" name="reminder_days" type="number" min="1" max="30" value="{{ old('reminder_days', $settings->reminder_days) }}" required /></div>
                    <div><label class="gym-label" for="shipping_fee">Phí giao hàng cố định (VNĐ)</label><x-form-input id="shipping_fee" name="shipping_fee" type="number" min="0" max="10000000" value="{{ old('shipping_fee', $settings->shipping_fee) }}" required /><p class="mt-2 text-xs text-slate-500">Nhận tại quầy luôn miễn phí; không áp dụng ưu đãi hạng khách vào phí giao.</p></div>
                </div>
                <div class="mt-5 rounded-xl bg-slate-50 p-4">
                    <input type="hidden" name="email_enabled" value="0">
                    <label class="flex items-center gap-3 text-sm font-semibold"><input class="size-4 rounded border-slate-300 text-gym-blue focus:ring-gym-blue" type="checkbox" name="email_enabled" value="1" @checked(old('email_enabled', $settings->email_enabled))>Bật email thông báo nghiệp vụ</label>
                    <p class="mt-2 text-xs leading-6 text-slate-600">Thông báo trong web luôn hoạt động. Driver email hiện tại: <strong>{{ $mailDriver }}</strong>. Với log/array, thư chỉ được lưu để kiểm tra; cần SMTP để gửi thật. Khôi phục mật khẩu dùng cấu hình mail chung.</p>
                </div>
            </section>

            <div class="flex justify-end border-t border-slate-200 pt-6"><button class="gym-btn-primary" type="submit">Lưu cài đặt</button></div>
        </form>

        <aside class="gym-card mt-6">
            <h2 class="text-lg font-bold">Tác vụ tự động</h2>
            <p class="mt-3 text-sm leading-7">Lần xử lý gần nhất: {{ $settings->last_processed_at ?: 'Chưa chạy' }}. Email thông báo gửi lỗi: {{ $failedEmails }}. Cần duy trì Laravel Scheduler để xử lý đơn quá hạn và gửi nhắc lịch.</p>
            <p class="mt-2 text-sm text-slate-500">Đơn đã nhận một phần tiền không tự hủy. Hóa đơn lịch sử chưa có hạn thanh toán được giữ để nhân viên đối chiếu.</p>
        </aside>

        <section class="gym-card mt-6" aria-labelledby="reconciliation-title">
            <h2 class="text-xl font-bold" id="reconciliation-title">Dữ liệu cần đối chiếu</h2>
            <p class="mt-2 text-sm leading-7 text-slate-500">Các danh sách dưới đây lấy từ dữ liệu hiện tại. Chỉ sửa khi có thông tin đúng; không tạo chi tiết hóa đơn, ảnh hay giá dịch vụ giả để lấp chỗ trống.</p>
            <div class="mt-5 grid gap-6 md:grid-cols-2">
                <div><h3 class="font-bold">Hóa đơn thiếu/lệch chi tiết hoặc tổng tiền</h3><ul class="mt-3 space-y-2 text-sm">@forelse ($incompleteInvoices as $row)<li><a class="text-gym-blue underline" href="{{ route('gym.invoices.show', $row->MAHD) }}">#{{ $row->MAHD }} · {{ $row->TRANGTHAI }}</a></li>@empty<li>Không phát hiện hóa đơn cần đối chiếu.</li>@endforelse</ul></div>
                <div><h3 class="font-bold">Ảnh sản phẩm cần cập nhật</h3><ul class="mt-3 max-h-48 space-y-2 overflow-y-auto text-sm">@forelse ($missingImages as $row)<li><a class="text-gym-blue underline" href="{{ route('gym.admin.edit', ['products', $row->MASP]) }}">#{{ $row->MASP }} · {{ $row->TENSP }}</a></li>@empty<li>Ảnh đại diện đã đầy đủ.</li>@endforelse</ul></div>
                <div><h3 class="font-bold">PT thiếu giá hoặc chuyên môn</h3><ul class="mt-3 space-y-2 text-sm">@forelse ($unreadyTrainers as $row)<li><a class="text-gym-blue underline" href="{{ route('gym.admin.edit', ['employees', $row->MANV]) }}">{{ $row->TENNV }}</a></li>@empty<li>PT đã có giá và chuyên môn.</li>@endforelse</ul></div>
                <div><h3 class="font-bold">Lớp chưa kết thúc: {{ $currentClasses }}</h3><p class="mt-2 text-sm leading-7 text-slate-500">Lịch sử được giữ nguyên. Để nhận đăng ký mới, tạo lớp có ngày bắt đầu trong tương lai rồi phân công lịch và huấn luyện viên.</p><a class="mt-3 inline-block text-sm text-gym-blue underline" href="{{ route('gym.admin.create', 'classes') }}">Tạo lớp hiện hành</a></div>
            </div>
        </section>
    </section>
@endsection
