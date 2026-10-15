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
                <x-input name="name" label="Họ và tên" :value="$employee->name" maxlength="255" autocomplete="name" required autofocus />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-input name="username" label="Tên đăng nhập" :value="$employee->username" maxlength="50" autocomplete="username" required />
                    <x-input name="phone" label="Số điện thoại" :value="$employee->phone" type="tel" maxlength="20" autocomplete="tel" required />
                </div>
                <x-input name="email" label="Email" :value="$employee->email" type="email" maxlength="255" autocomplete="email" required />
                <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-950">
                    <label class="gym-label" for="password">{{ $isEditing ? 'Mật khẩu mới (không bắt buộc)' : 'Mật khẩu' }}</label>
                    <input class="gym-input" id="password" name="password" type="password" autocomplete="new-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @if ($errors->has('password')) aria-describedby="password-error" @endif @required(! $isEditing)>
                    @error('password')
                        <p class="mt-1 text-sm font-medium text-red-700" id="password-error">{{ $message }}</p>
                    @enderror
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
