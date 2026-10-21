@extends('layouts.app')

@section('title', 'Kiểm tra file Excel')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-7">
            <a class="font-semibold text-gym-blue underline underline-offset-4" href="{{ route('manager.knowledge.index') }}">← Danh sách tài liệu</a>
            <p class="mt-5 text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Nhập Excel</p>
            <h1 class="gym-title mt-2">Kiểm tra trước khi nhập</h1>
            <p class="mt-2 text-slate-600">Chưa có thay đổi nào được gửi cho chatbot. Kiểm tra danh sách và lỗi dưới đây trước khi xác nhận.</p>
        </header>

        @if (empty($preview['errors']))
            <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-950" role="status">
                File hợp lệ: <strong>{{ $preview['new_count'] }} tài liệu mới</strong> và <strong>{{ $preview['update_count'] }} tài liệu sẽ được cập nhật</strong>.
            </div>
        @else
            <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-red-950" role="alert">
                <h2 class="font-bold">Xử lý các lỗi trước khi xác nhận</h2>
                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach ($preview['errors'] as $error)
                        <li><strong>Dòng {{ $error['row'] }}:</strong> {{ $error['message'] }}</li>
                    @endforeach
                </ul>
                <p class="mt-2 text-sm">Không có dòng nào được nhập khi file còn lỗi.</p>
            </div>
        @endif

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm" tabindex="0" aria-label="Danh sách câu hỏi sắp nhập">
            <table class="gym-table min-w-[48rem]">
                <thead><tr><th scope="col">Dòng</th><th scope="col">Chủ đề</th><th scope="col">Câu hỏi</th><th scope="col">Thao tác</th><th scope="col">Trạng thái</th></tr></thead>
                <tbody>
                    @forelse ($preview['rows'] as $row)
                        <tr>
                            <td>{{ $row['row'] }}</td>
                            <td>{{ $row['topic'] }}</td>
                            <td>{{ $row['question'] }}</td>
                            <td>{{ $row['action'] === 'create' ? 'Thêm mới' : 'Cập nhật' }}</td>
                            <td>{{ $row['active'] ? 'Đang dùng' : 'Tạm ngừng' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-slate-600">Chưa có dòng hợp lệ để xem trước.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex flex-wrap justify-between gap-3">
            <a class="gym-btn-outline" href="{{ route('manager.knowledge.index') }}">Quay lại tải file</a>
            @if (empty($preview['errors']) && ! empty($preview['documents']))
                <form method="POST" action="{{ route('manager.knowledge.import.confirm') }}">
                    @csrf
                    <button class="gym-btn-primary" type="submit">Xác nhận nhập tài liệu</button>
                </form>
            @endif
        </div>
    </section>
@endsection
