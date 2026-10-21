@extends('layouts.app')

@section('title', 'Tài liệu chatbot')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Nội dung trợ lý The Gym</p>
                <h1 class="gym-title mt-2">Tài liệu chatbot</h1>
                <p class="mt-2 max-w-3xl text-slate-600">Cập nhật câu hỏi thường gặp hoặc hướng dẫn dài để trợ lý trả lời đúng nghiệp vụ phòng tập. Chỉ nhập thông tin công khai; không đưa mật khẩu, mã OTP hay dữ liệu hội viên lên đây.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a class="gym-btn-primary" href="{{ route('manager.knowledge.faq.create') }}">Thêm câu hỏi</a>
                <a class="gym-btn-outline" href="{{ route('manager.knowledge.create') }}">Thêm hướng dẫn dài</a>
            </div>
        </header>

        @if ($serviceError)
            <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-4 leading-6 text-amber-950" role="alert">
                {{ $serviceError }} Danh sách chưa được tải; kiểm tra dịch vụ chatbot rồi tải lại trang.
            </div>
        @endif

        <section class="gym-card mb-6 p-5 sm:p-6" aria-labelledby="faq-import-title">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div><h2 class="text-lg font-bold text-gym-ink" id="faq-import-title">Nhập nhiều câu hỏi bằng Excel</h2><p class="mt-1 text-sm leading-6 text-slate-600">Tải mẫu, điền câu hỏi rồi tải lên để xem trước. Hệ thống kiểm tra toàn bộ file trước khi cập nhật chatbot.</p></div>
                <a class="font-semibold text-gym-blue underline underline-offset-4" href="{{ route('manager.knowledge.import.template') }}">Tải file mẫu Excel</a>
            </div>
            <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" method="POST" action="{{ route('manager.knowledge.import.preview') }}" enctype="multipart/form-data">
                @csrf
                <div class="flex-1"><label class="gym-label" for="knowledge-excel">Chọn file `.xlsx` (tối đa 5 MB, 100 câu hỏi)</label><input class="gym-input" id="knowledge-excel" name="file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></div>
                <button class="gym-btn-primary shrink-0" type="submit">Kiểm tra file</button>
            </form>
        </section>

        <form class="mb-4 flex flex-col gap-3 sm:flex-row" method="GET" action="{{ route('manager.knowledge.index') }}">
            <label class="sr-only" for="knowledge-search">Tìm tài liệu theo chủ đề hoặc câu hỏi</label>
            <x-form-input class="flex-1" id="knowledge-search" name="q" type="search" value="{{ $search }}" placeholder="Tìm theo chủ đề hoặc câu hỏi" />
            <button class="gym-btn-outline" type="submit">Tìm tài liệu</button>
            @if ($search !== '')<a class="gym-btn-outline" href="{{ route('manager.knowledge.index') }}">Xóa tìm kiếm</a>@endif
        </form>

        <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><h2 class="text-xl font-extrabold">Nội dung đã có</h2><p class="text-sm text-slate-600">{{ number_format($documents->total()) }} tài liệu</p></div>
        <div class="grid gap-3">
            @forelse ($documents as $document)
                <article class="gym-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="text-lg font-bold text-gym-ink">{{ $document['title'] }}</h3>
                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $document['active'] ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-200 text-slate-700' }}">{{ $document['active'] ? 'Đang dùng' : 'Tạm ngừng' }}</span>
                        </div>
                        @if ($document['summary'] !== '')<p class="mt-2 text-sm text-slate-600">Câu hỏi: {{ $document['summary'] }}</p>@endif
                        <p class="mt-2 text-xs text-slate-500">Cập nhật lần cuối: {{ $document['updated_at'] }}</p>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-x-4 gap-y-2 sm:justify-end">
                        <a class="font-semibold text-gym-blue underline underline-offset-4" href="{{ route('manager.knowledge.edit', $document['id']) }}">Sửa</a>
                        <form method="POST" action="{{ route('manager.knowledge.toggle', $document['id']) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="active" value="{{ $document['active'] ? '0' : '1' }}">
                            <button class="font-semibold underline underline-offset-4 {{ $document['active'] ? 'text-amber-700' : 'text-emerald-700' }}" type="submit">{{ $document['active'] ? 'Tạm ngừng' : 'Bật lại' }}</button>
                        </form>
                        <form method="POST" action="{{ route('manager.knowledge.destroy', $document['id']) }}" onsubmit="return confirm('Xóa hẳn nội dung này khỏi chatbot?')">
                            @csrf @method('DELETE')
                            <button class="font-semibold text-red-700 underline underline-offset-4" type="submit">Xóa</button>
                        </form>
                    </div>
                </article>
            @empty
                @unless ($serviceError)
                    <p class="gym-card p-8 text-center text-slate-600">{{ $search !== '' ? 'Không tìm thấy nội dung phù hợp.' : 'Chưa có tài liệu. Thêm câu hỏi hoặc hướng dẫn công khai để chatbot trả lời.' }}</p>
                @endunless
            @endforelse
        </div>
        <nav class="mt-6" aria-label="Các trang tài liệu chatbot">{{ $documents->links() }}</nav>
    </section>
@endsection
