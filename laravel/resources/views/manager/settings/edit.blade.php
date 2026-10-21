@extends('layouts.app')

@section('title', 'Cài đặt phòng tập')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-8"><p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Thiết lập vận hành</p><h1 class="gym-title mt-2">Thông tin phòng tập</h1><p class="mt-2 text-slate-600">Thông tin này được dùng trên trang web và trong hướng dẫn thanh toán.</p></header>
        <form class="gym-card mx-auto max-w-4xl p-6 sm:p-8" method="POST" action="{{ route('manager.settings.update') }}">
            @csrf @method('PUT')
            <div class="grid gap-5 sm:grid-cols-2">
                <div><label class="gym-label" for="gym_name">Tên phòng tập</label><x-form-input id="gym_name" name="gym_name" value="{{ old('gym_name', $settings->gym_name) }}" maxlength="120" required /></div>
                <div><label class="gym-label" for="phone">Số điện thoại</label><x-form-input id="phone" name="phone" type="tel" value="{{ old('phone', $settings->phone) }}" maxlength="30" /></div>
                <div><label class="gym-label" for="email">Email liên hệ</label><x-form-input id="email" name="email" type="email" value="{{ old('email', $settings->email) }}" maxlength="255" /></div>
                <div><label class="gym-label" for="opening_hours">Giờ mở cửa</label><x-form-input id="opening_hours" name="opening_hours" value="{{ old('opening_hours', $settings->opening_hours) }}" maxlength="255" placeholder="Ví dụ: 06:00–22:00 mỗi ngày" /></div>
                <div class="sm:col-span-2"><label class="gym-label" for="address">Địa chỉ</label><x-form-input id="address" name="address" value="{{ old('address', $settings->address) }}" maxlength="500" /></div>
                <div class="sm:col-span-2"><label class="gym-label" for="introduction">Giới thiệu ngắn</label><x-form-textarea id="introduction" name="introduction" rows="4" maxlength="3000">{{ old('introduction', $settings->introduction) }}</x-form-textarea></div>
            </div>
            <div class="my-7 border-t border-slate-200"></div>
            <h2 class="text-lg font-bold">Hướng dẫn chuyển khoản</h2><p class="mt-1 text-sm text-slate-600">Nhân viên vẫn cần kiểm tra tiền đã nhận trước khi xác nhận đơn.</p>
            <div class="mt-4 grid gap-5 sm:grid-cols-3">
                <div><label class="gym-label" for="bank_name">Ngân hàng</label><x-form-input id="bank_name" name="bank_name" value="{{ old('bank_name', $settings->bank_name) }}" maxlength="120" /></div>
                <div><label class="gym-label" for="bank_account">Số tài khoản</label><x-form-input id="bank_account" name="bank_account" value="{{ old('bank_account', $settings->bank_account) }}" maxlength="80" /></div>
                <div><label class="gym-label" for="bank_account_name">Chủ tài khoản</label><x-form-input id="bank_account_name" name="bank_account_name" value="{{ old('bank_account_name', $settings->bank_account_name) }}" maxlength="160" /></div>
            </div>
            <div class="my-7 border-t border-slate-200"></div>
            <h2 class="text-lg font-bold">Câu hỏi thường gặp</h2>
            <p class="mt-1 text-sm text-slate-600">Câu trả lời sẽ hiện công khai trên trang chủ. Chỉ nhập thông tin đã được phòng tập xác nhận.</p>
            @php($faqItems = old('faq_items', $settings->faq_items ?? []))
            <div class="mt-4 grid gap-5">
                @foreach (range(0, 3) as $faqIndex)
                    <fieldset class="grid gap-4 rounded-2xl border border-slate-200 p-4 sm:grid-cols-2">
                        <legend class="px-2 text-sm font-semibold text-slate-700">Câu hỏi {{ $faqIndex + 1 }}</legend>
                        <div><label class="gym-label" for="faq-question-{{ $faqIndex }}">Câu hỏi</label><x-form-input id="faq-question-{{ $faqIndex }}" name="faq_items[{{ $faqIndex }}][question]" value="{{ $faqItems[$faqIndex]['question'] ?? '' }}" maxlength="180" /></div>
                        <div><label class="gym-label" for="faq-answer-{{ $faqIndex }}">Câu trả lời</label><x-form-textarea id="faq-answer-{{ $faqIndex }}" name="faq_items[{{ $faqIndex }}][answer]" rows="2" maxlength="800">{{ $faqItems[$faqIndex]['answer'] ?? '' }}</x-form-textarea></div>
                    </fieldset>
                @endforeach
            </div>
            <div class="mt-7 flex justify-end"><button class="gym-btn-primary" type="submit">Lưu cài đặt</button></div>
        </form>
    </section>
@endsection
