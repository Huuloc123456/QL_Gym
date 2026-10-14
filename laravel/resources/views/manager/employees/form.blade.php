@extends('layouts.app')

@section('title', $isEditing ? 'Sửa tài khoản nhân viên' : 'Tạo tài khoản nhân viên')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <div class="mx-auto max-w-2xl">
            <header class="mb-6">
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Khu vực quản lý</p>
                <h1 class="gym-title mt-2">{{ $isEditing ? 'Sửa tài khoản nhân viên' : 'Tạo tài khoản nhân viên' }}</h1>
                <p class="mt-2 text-slate-600">Tài khoản được tạo với quyền nhân viên. Mật khẩu chỉ được lưu dưới dạng đã mã hóa.</p>
            </header>

            <form class="gym-card space-y-5 p-6 sm:p-8" method="POST" action="{{ $isEditing ? route('manager.employees.update', $employee) : route('manager.employees.store') }}">
                @csrf
                @if ($isEditing)
                    @method('PUT')
                @endif
                <div>
                    <label class="gym-label" for="name">Họ và tên</label>
                    <input class="gym-input" id="name" name="name" value="{{ old('name', $employee->name) }}" maxlength="255" autocomplete="name" required autofocus>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="gym-label" for="username">Tên đăng nhập</label>
                        <input class="gym-input" id="username" name="username" value="{{ old('username', $employee->username) }}" maxlength="50" autocomplete="username" required>
                    </div>
                    <div>
                        <label class="gym-label" for="phone">Số điện thoại</label>
                        <input class="gym-input" id="phone" name="phone" type="tel" value="{{ old('phone', $employee->phone) }}" maxlength="20" autocomplete="tel" required>
                    </div>
                </div>
                <div>
                    <label class="gym-label" for="email">Email</label>
                    <input class="gym-input" id="email" name="email" type="email" value="{{ old('email', $employee->email) }}" maxlength="255" autocomplete="email" required>
                </div>
                <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-950">
                    <label class="gym-label" for="password">{{ $isEditing ? 'Mật khẩu mới (không bắt buộc)' : 'Mật khẩu' }}</label>
                    <input class="gym-input" id="password" name="password" type="password" autocomplete="new-password" @required(! $isEditing)>
                    <p class="mt-2">{{ $isEditing ? 'Để trống nếu không đổi mật khẩu.' : 'Mật khẩu cần ít nhất 8 ký tự.' }}</p>
                    <label class="gym-label mt-4" for="password_confirmation">{{ $isEditing ? 'Nhập lại mật khẩu mới' : 'Nhập lại mật khẩu' }}</label>
                    <input class="gym-input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(! $isEditing)>
                </div>
                <div class="flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
                    <a class="gym-btn-outline" href="{{ route('manager.employees.index') }}">Hủy</a>
                    <button class="gym-btn-primary" type="submit">{{ $isEditing ? 'Lưu thay đổi' : 'Tạo tài khoản' }}</button>
                </div>
            </form>
        </div>
    </section>
@endsection
