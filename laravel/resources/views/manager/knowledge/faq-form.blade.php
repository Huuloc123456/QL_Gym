@extends('layouts.app')

@section('title', $isEditing ? 'Sửa câu hỏi thường gặp' : 'Thêm câu hỏi thường gặp')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <div class="mx-auto max-w-3xl">
            <a class="font-semibold text-gym-blue underline underline-offset-4" href="{{ route('manager.knowledge.index') }}">← Danh sách tài liệu</a>
            <header class="mb-6 mt-5">
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Câu hỏi thường gặp</p>
                <h1 class="gym-title mt-2">{{ $isEditing ? 'Sửa câu hỏi và câu trả lời' : 'Thêm câu hỏi mới' }}</h1>
                <p class="mt-2 leading-6 text-slate-600">Viết như khách hàng thường hỏi. Hệ thống tự tạo tài liệu cho chatbot và cập nhật chỉ mục sau khi lưu.</p>
            </header>

            <form class="gym-card space-y-5 p-6 sm:p-8" method="POST" action="{{ $isEditing ? route('manager.knowledge.faq.update', $document['id']) : route('manager.knowledge.faq.store') }}">
                @csrf
                @if ($isEditing)
                    @method('PUT')
                    <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">Tài liệu {{ $document['active'] ? 'đang được chatbot sử dụng' : 'đang tạm ngừng' }}.</p>
                @endif
                <div>
                    <label class="gym-label" for="faq-topic">Chủ đề</label>
                    <x-form-input id="faq-topic" name="topic" maxlength="160" value="{{ old('topic', $document['topic'] ?? '') }}" placeholder="Ví dụ: Gói tập, Thanh toán, Tài khoản" required />
                </div>
                <div>
                    <label class="gym-label" for="faq-question">Khách hàng sẽ hỏi</label>
                    <x-form-input id="faq-question" name="question" maxlength="500" value="{{ old('question', $document['question'] ?? '') }}" placeholder="Ví dụ: Gói tập một tháng có thời hạn thế nào?" required />
                </div>
                <div>
                    <label class="gym-label" for="faq-answer">Câu trả lời</label>
                    <textarea class="gym-input min-h-48 resize-y leading-6" id="faq-answer" name="answer" maxlength="20000" placeholder="Viết câu trả lời rõ ràng, đúng thông tin phòng tập." required>{{ old('answer', $document['answer'] ?? '') }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Không nhập mật khẩu, mã xác minh hay thông tin riêng của hội viên.</p>
                </div>
                <div class="flex flex-wrap justify-between gap-3 border-t border-slate-100 pt-5">
                    <a class="gym-btn-outline" href="{{ route('manager.knowledge.index') }}">Hủy</a>
                    <button class="gym-btn-primary" type="submit">{{ $isEditing ? 'Lưu câu hỏi' : 'Thêm câu hỏi' }}</button>
                </div>
            </form>
        </div>
    </section>
@endsection
