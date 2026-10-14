@extends('layouts.app')

@section('title', 'Tài khoản nhân viên')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Khu vực quản lý</p>
                <h1 class="gym-title mt-2">Tài khoản nhân viên</h1>
                <p class="mt-2 text-slate-600">Tạo tài khoản vận hành và cập nhật thông tin đăng nhập của nhân viên.</p>
            </div>
            <a class="gym-btn-primary" href="{{ route('manager.employees.create') }}">Tạo tài khoản nhân viên</a>
        </header>

        <form class="gym-card mb-6 grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-end" method="GET" action="{{ route('manager.employees.index') }}">
            <div>
                <label class="gym-label" for="employee-search">Tìm theo tên, tên đăng nhập, email hoặc số điện thoại</label>
                <input class="gym-input" id="employee-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Ví dụ: nhanvien">
            </div>
            <button class="gym-btn-primary w-full sm:w-auto" type="submit">Tìm nhân viên</button>
            <a class="gym-btn-outline w-full sm:w-auto" href="{{ route('manager.employees.index') }}">Xóa lọc</a>
        </form>

        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm" tabindex="0" aria-label="Danh sách nhân viên; vuốt ngang để xem thêm cột">
            <table class="gym-table min-w-[48rem]">
                <thead><tr><th scope="col">Nhân viên</th><th scope="col">Tên đăng nhập</th><th scope="col">Email</th><th scope="col">Điện thoại</th><th scope="col"><span class="sr-only">Thao tác</span></th></tr></thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td class="font-semibold text-gym-ink">{{ $employee->name }}</td>
                            <td>{{ $employee->username }}</td>
                            <td>{{ $employee->email }}</td>
                            <td>{{ $employee->phone }}</td>
                            <td><a class="font-semibold text-gym-blue underline underline-offset-4" href="{{ route('manager.employees.edit', $employee) }}">Sửa tài khoản<span class="sr-only"> của {{ $employee->name }}</span></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-slate-600">Chưa có nhân viên khớp với thông tin tìm kiếm.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($employees->hasPages())
            <nav class="mt-6" aria-label="Các trang nhân viên">{{ $employees->links() }}</nav>
        @endif
        <p class="mt-6 text-sm text-slate-500"><a class="font-semibold text-gym-blue underline underline-offset-4" href="{{ route('manager.dashboard') }}">Quay lại tổng quan quản lý</a></p>
    </section>
@endsection
