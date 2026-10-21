@extends('layouts.app')

@section('title', $isEditing ? 'Sửa tài liệu chatbot' : 'Thêm tài liệu chatbot')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <div class="mx-auto max-w-4xl">
            <a class="font-semibold text-gym-blue underline underline-offset-4" href="{{ route('manager.knowledge.index') }}">← Danh sách tài liệu</a>
            <header class="mb-6 mt-5">
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Nội dung chatbot</p>
                <h1 class="gym-title mt-2">{{ $isEditing ? 'Sửa tài liệu' : 'Thêm tài liệu' }}</h1>
                <p class="mt-2 leading-6 text-slate-600">Viết câu trả lời rõ, chính xác và dành cho khách hàng. Khi lưu, hệ thống dựng lại chỉ mục; thao tác có thể mất vài phút. Nếu cập nhật lỗi thì chatbot giữ bản chỉ mục trước đó.</p>
            </header>

            <form class="gym-card space-y-5 p-6 sm:p-8" method="POST" action="{{ $isEditing ? route('manager.knowledge.update', $document['id']) : route('manager.knowledge.store') }}">
                @csrf
                @if ($isEditing)
                    @method('PUT')
                    <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">Mã tài liệu: <span class="font-mono">{{ $document['id'] }}</span> · trạng thái {{ $document['active'] ? 'đang dùng' : 'đã tạm ngừng' }}</p>
                @endif
                <div>
                    <label class="gym-label" for="knowledge-title">Tiêu đề</label>
                    <x-form-input id="knowledge-title" name="title" maxlength="160" value="{{ old('title', $document['title'] ?? '') }}" required />
                    <p class="mt-1 text-xs text-slate-500">Tiêu đề tạo mã tài liệu tự động khi thêm mới.</p>
                </div>
                <div>
                    <label class="gym-label" for="knowledge-content">Nội dung hướng dẫn</label>
                    <textarea class="gym-input min-h-80 resize-y text-sm leading-6" id="knowledge-content" name="content" maxlength="199000" required>{{ old('content', $document['content'] ?? '') }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Tối đa 200 KB. Viết nội dung dễ hiểu; có thể dùng gạch đầu dòng để chia ý. Không dán dữ liệu cá nhân hoặc thông tin đăng nhập.</p>
                </div>
                <div class="flex flex-wrap justify-between gap-3 border-t border-slate-100 pt-5">
                    <a class="gym-btn-outline" href="{{ route('manager.knowledge.index') }}">Hủy</a>
                    <button class="gym-btn-primary" type="submit">{{ $isEditing ? 'Lưu và cập nhật chatbot' : 'Thêm và cập nhật chatbot' }}</button>
                </div>
            </form>
        </div>
    </section>
@endsection
