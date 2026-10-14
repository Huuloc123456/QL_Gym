@extends('layouts.app')

@section('title', 'Quản lý gói tập')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div><p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Quản lý</p><h1 class="gym-title mt-2">Quản lý gói tập</h1><p class="mt-2 text-slate-600">Cập nhật thông tin và trạng thái mở bán các gói tập.</p></div>
            <a class="gym-btn-primary" href="{{ route('manager.packages.create') }}">Tạo gói tập mới</a>
        </header>
        <a class="font-semibold text-gym-blue underline underline-offset-4" href="{{ route('manager.dashboard') }}">← Tổng quan quản lý</a>
        <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm" tabindex="0" aria-label="Danh sách gói tập; vuốt ngang để xem thêm cột">
            <table class="gym-table">
                <thead><tr><th scope="col">Gói tập</th><th scope="col">Thời hạn</th><th scope="col">Giá</th><th scope="col">Trạng thái</th><th scope="col">Thao tác</th></tr></thead>
                <tbody>
                    @forelse ($packages as $package)
                        <tr>
                            <td class="font-semibold">{{ $package->name }}</td>
                            <td class="whitespace-nowrap">{{ $package->duration_months }} tháng</td>
                            <td class="whitespace-nowrap font-semibold tabular-nums">{{ number_format($package->price, 0, ',', '.') }} VNĐ</td>
                            <td>{{ $package->is_active ? 'Đang mở bán' : 'Ngừng bán' }}</td>
                            <td><div class="flex flex-wrap items-center gap-3">
                                <a class="gym-btn-outline min-h-10 px-4 py-2 text-sm" href="{{ route('manager.packages.edit', $package) }}" aria-label="Sửa {{ $package->name }}">Sửa</a>
                                <form method="POST" action="{{ route('manager.packages.toggle-active', $package) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="gym-btn-outline min-h-10 px-4 py-2 text-sm" type="submit" aria-label="{{ $package->is_active ? 'Ngừng bán' : 'Mở bán' }} {{ $package->name }}">{{ $package->is_active ? 'Ngừng bán' : 'Mở bán' }}</button>
                                </form>
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-8 text-center text-slate-500">Chưa có gói tập. Tạo gói mới để bắt đầu mở bán.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="mt-4 text-sm text-slate-600">Ngừng bán chỉ ẩn gói khỏi danh sách đăng ký; lịch sử đơn và hội viên được giữ lại.</p>
    </section>
@endsection
