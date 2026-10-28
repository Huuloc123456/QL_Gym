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
                <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div><label class="gym-label" for="bank_name">Tên ngân hàng</label><x-form-input id="bank_name" name="bank_name" value="{{ old('bank_name', $settings->bank_name) }}" maxlength="120" /></div>
                    <div><label class="gym-label" for="bank_code">Mã ngân hàng VietQR</label><x-form-input id="bank_code" name="bank_code" value="{{ old('bank_code', $settings->bank_code) }}" maxlength="20" placeholder="vd: vietcombank" /><p class="mt-1 text-xs text-slate-500">Dùng mã VietQR như vietcombank, mbbank hoặc mã BIN.</p></div>
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
                    <div><label class="gym-label" for="trial_days">Thời lượng tập thử (ngày)</label><x-form-input id="trial_days" name="trial_days" type="number" min="1" max="7" value="{{ old('trial_days', $settings->trial_days) }}" required /></div>
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
            @php($schedulerLastRun = $settings->last_processed_at ? \Illuminate\Support\Carbon::parse($settings->last_processed_at) : null)
            @php($schedulerIsLate = ! $schedulerLastRun || $schedulerLastRun->lt(now()->subMinutes(3)))
            @if ($schedulerIsLate)
                <div class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950" role="status">
                    <strong>Scheduler chưa chạy gần đây.</strong>
                    <p class="mt-1">Tác vụ xử lý đơn quá hạn và nhắc lịch có thể đang dừng. Trên máy local, chạy <code>php artisan schedule:work</code> trong cửa sổ terminal riêng.</p>
                </div>
            @else
                <p class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-950" role="status"><strong>Scheduler đang hoạt động.</strong> Lần xử lý gần nhất: {{ $schedulerLastRun->format('d/m/Y H:i:s') }}.</p>
            @endif
            <p class="mt-3 text-sm leading-7">Email thông báo gửi lỗi: {{ $failedEmails }}. Máy chủ triển khai cần gọi `php artisan schedule:run` mỗi phút.</p>
            <p class="mt-2 text-sm text-slate-500">Đơn đã nhận một phần tiền không tự hủy. Hóa đơn lịch sử chưa có hạn thanh toán được giữ để nhân viên đối chiếu.</p>
        </aside>

        <section class="gym-card mt-6" aria-labelledby="reconciliation-title">
            <h2 class="text-xl font-bold" id="reconciliation-title">Dữ liệu cần đối chiếu</h2>
            <p class="mt-2 text-sm leading-7 text-slate-500">Mỗi hóa đơn hiển thị riêng hai nguồn dòng hàng, khoản thu/hoàn và liên kết gói. Ghi người phụ trách, bằng chứng và hướng xử lý; không tự điền dữ liệu hóa đơn còn thiếu. Hóa đơn còn sai lệch vẫn bị chặn thu và chốt.</p>
            @php($categoryLabels = ['missing_details' => 'Thiếu chi tiết', 'detail_sources_disagree' => 'Hai nguồn chi tiết không khớp', 'detail_total_mismatch' => 'Tổng dòng hàng không khớp', 'payable_mismatch' => 'Thành tiền không khớp', 'payment_mismatch' => 'Khoản thu/trạng thái không khớp', 'package_link_mismatch' => 'Liên kết đơn gói không khớp', 'legacy_service' => 'Dịch vụ cũ không còn hỗ trợ', 'other' => 'Khác'])
            <div class="mt-5 space-y-5">
                @forelse ($incompleteInvoices as $row)
                    <article class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 sm:p-5">
                        <header class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-bold"><a class="text-gym-blue underline" href="{{ route('gym.invoices.show', $row->invoice_id) }}">Hóa đơn #{{ $row->invoice_id }}</a> · {{ $row->invoice_status }}</h3>
                                <p class="mt-1 text-sm text-slate-600">Khách #{{ $row->customer_id }} · {{ \Illuminate\Support\Carbon::parse($row->invoice_date)->format('d/m/Y H:i') }}</p>
                            </div>
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-950">{{ $row->review_status ? ['in_review' => 'Đang xác minh', 'blocked' => 'Chặn chờ chứng từ'][$row->review_status] ?? $row->review_status : 'Chưa ghi nhận đối soát' }}</span>
                        </header>
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                            <div class="rounded-lg bg-white p-3"><dt class="text-slate-500">Tổng / giảm / phải trả</dt><dd class="mt-1 font-semibold">{{ number_format($row->gross_total, 0, ',', '.') }} / {{ number_format($row->discount_total ?? 0, 0, ',', '.') }} / {{ number_format($row->payable_total, 0, ',', '.') }}đ</dd></div>
                            <div class="rounded-lg bg-white p-3"><dt class="text-slate-500">Chi tiết SQL gốc</dt><dd class="mt-1 font-semibold">{{ $row->legacy_line_count }} dòng · {{ number_format($row->legacy_line_total, 0, ',', '.') }}đ</dd></div>
                            <div class="rounded-lg bg-white p-3"><dt class="text-slate-500">Chi tiết website</dt><dd class="mt-1 font-semibold">{{ $row->current_line_count }} dòng · {{ number_format($row->current_line_total, 0, ',', '.') }}đ</dd></div>
                            <div class="rounded-lg bg-white p-3"><dt class="text-slate-500">Thu / hoàn</dt><dd class="mt-1 font-semibold">{{ $row->receipt_count }} khoản · {{ number_format($row->receipt_total, 0, ',', '.') }}đ / {{ number_format($row->online_payment_total, 0, ',', '.') }}đ online ({{ $row->online_payment_states ?: 'không có' }}) / hoàn {{ number_format($row->refund_total, 0, ',', '.') }}đ</dd></div>
                            <div class="rounded-lg bg-white p-3"><dt class="text-slate-500">Liên kết đơn gói</dt><dd class="mt-1 font-semibold">{{ $row->package_order_count }} đơn</dd></div>
                            <div class="rounded-lg bg-white p-3 sm:col-span-2 lg:col-span-3"><dt class="text-slate-500">Bằng chứng đã ghi</dt><dd class="mt-1">{{ $row->evidence ?: 'Chưa ghi nhận.' }}</dd></div>
                        </dl>
                        @if ($row->note)
                            <p class="mt-3 text-sm"><strong>Hướng xử lý:</strong> {{ $row->note }}</p>
                        @endif
                        <form class="mt-5 grid gap-4 border-t border-amber-200 pt-4 sm:grid-cols-2" method="POST" action="{{ route('manager.reconciliations.update', $row->invoice_id) }}">
                            @csrf
                            @method('PUT')
                            <div><label class="gym-label" for="reconciliation-category-{{ $row->invoice_id }}">Nhóm sai lệch</label><select class="gym-input" id="reconciliation-category-{{ $row->invoice_id }}" name="category" required>@foreach ($categoryLabels as $value => $label)<option value="{{ $value }}" @selected(old('category', $row->review_category ?? $row->suggested_category) === $value)>{{ $label }}</option>@endforeach</select>@error('category')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                            <div><label class="gym-label" for="reconciliation-status-{{ $row->invoice_id }}">Trạng thái</label><select class="gym-input" id="reconciliation-status-{{ $row->invoice_id }}" name="status" required>@foreach (['in_review' => 'Đang xác minh', 'blocked' => 'Chặn chờ chứng từ', 'resolved' => 'Đã xử lý'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $row->review_status ?? 'blocked') === $value)>{{ $label }}</option>@endforeach</select></div>
                            <div><label class="gym-label" for="reconciliation-owner-{{ $row->invoice_id }}">Quản lý phụ trách</label><select class="gym-input" id="reconciliation-owner-{{ $row->invoice_id }}" name="owner_user_id" required>@foreach ($reconciliationManagers as $manager)<option value="{{ $manager->id }}" @selected((int) old('owner_user_id', $row->owner_user_id ?? auth()->id()) === (int) $manager->id)>{{ $manager->name }}</option>@endforeach</select></div>
                            <div><label class="gym-label" for="reconciliation-evidence-{{ $row->invoice_id }}">Bằng chứng đã kiểm tra</label><textarea class="gym-input min-h-20" id="reconciliation-evidence-{{ $row->invoice_id }}" name="evidence" minlength="8" maxlength="2000" required>{{ old('evidence', $row->evidence) }}</textarea>@error('evidence')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                            <div class="sm:col-span-2"><label class="gym-label" for="reconciliation-note-{{ $row->invoice_id }}">Lý do / bước tiếp theo</label><textarea class="gym-input min-h-20" id="reconciliation-note-{{ $row->invoice_id }}" name="note" minlength="8" maxlength="2000" required>{{ old('note', $row->note) }}</textarea>@error('note')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
                            @error('status')<p class="text-sm font-semibold text-red-700 sm:col-span-2">{{ $message }}</p>@enderror
                            @error('owner_user_id')<p class="text-sm font-semibold text-red-700 sm:col-span-2">{{ $message }}</p>@enderror
                            <button class="gym-btn sm:justify-self-start" type="submit">Lưu đối soát hóa đơn #{{ $row->invoice_id }}</button>
                        </form>
                    </article>
                @empty
                    <p class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950">Không phát hiện hóa đơn cần đối chiếu.</p>
                @endforelse
            </div>
            @if ($resolvedReconciliations->isNotEmpty())
                <div class="mt-6 border-t border-slate-200 pt-5">
                    <h3 class="font-bold">Đối soát đã xử lý gần đây</h3>
                    <ul class="mt-3 space-y-3 text-sm">@foreach ($resolvedReconciliations as $resolved)<li class="rounded-lg bg-slate-50 p-3"><strong>Hóa đơn #{{ $resolved->invoice_id }}</strong> · {{ $categoryLabels[$resolved->category] ?? $resolved->category }} · {{ \Illuminate\Support\Carbon::parse($resolved->reviewed_at)->format('d/m/Y H:i') }}<br><span class="text-slate-600">Phụ trách: {{ $resolved->owner_name }} · Ghi nhận bởi: {{ $resolved->reviewer_name }} · {{ $resolved->note }}</span></li>@endforeach</ul>
                </div>
            @endif
            <div class="mt-8 grid gap-6 border-t border-slate-200 pt-6 md:grid-cols-2">
                <div><h3 class="font-bold">Ảnh sản phẩm cần cập nhật</h3><ul class="mt-3 max-h-48 space-y-2 overflow-y-auto text-sm">@forelse ($missingImages as $row)<li><a class="text-gym-blue underline" href="{{ route('gym.admin.edit', ['products', $row->MASP]) }}">#{{ $row->MASP }} · {{ $row->TENSP }}</a></li>@empty<li>Ảnh đại diện đã đầy đủ.</li>@endforelse</ul></div>
                <div><h3 class="font-bold">Lớp chưa kết thúc: {{ $currentClasses }}</h3><p class="mt-2 text-sm leading-7 text-slate-500">Lịch sử được giữ nguyên. Để nhận đăng ký mới, tạo lớp có ngày bắt đầu trong tương lai rồi xếp lịch và phân công nhân viên phụ trách.</p><a class="mt-3 inline-block text-sm text-gym-blue underline" href="{{ route('gym.admin.create', 'classes') }}">Tạo lớp hiện hành</a></div>
            </div>
        </section>
    </section>
@endsection
