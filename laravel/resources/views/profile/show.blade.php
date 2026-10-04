@extends('layouts.app')

@section('title', 'Thông tin cá nhân')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <div class="mx-auto max-w-3xl">
            <div class="gym-card p-6 sm:p-9">
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Tài khoản của bạn</p>
                <h1 class="gym-title mt-2">Thông tin cá nhân</h1>
                <p class="mt-3 text-sm leading-6 text-slate-600">Mã hội viên #{{ $customer->id }} · Tên đăng nhập và email được giữ cố định để bảo vệ tài khoản.</p>
                <form class="mt-6 grid gap-4 sm:grid-cols-2" method="POST" action="{{ route('my.profile.update') }}">
                    @csrf
                    @method('PUT')
                    <div><label class="gym-label" for="name">Họ và tên</label><input class="gym-input" id="name" name="name" autocomplete="name" value="{{ old('name', $customer->name) }}" required></div>
                    <div><label class="gym-label" for="phone">Số điện thoại</label><input class="gym-input" id="phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone', $customer->phone) }}" required></div>
                    <div><label class="gym-label" for="birth_date">Ngày sinh</label><input class="gym-input" id="birth_date" name="birth_date" type="date" autocomplete="bday" value="{{ old('birth_date', $customer->birth_date?->toDateString()) }}"></div>
                    <div><label class="gym-label" for="gender">Giới tính</label><select class="gym-input" id="gender" name="gender"><option value="">Chưa chọn</option><option value="male" @selected(old('gender', $customer->gender) === 'male')>Nam</option><option value="female" @selected(old('gender', $customer->gender) === 'female')>Nữ</option><option value="other" @selected(old('gender', $customer->gender) === 'other')>Khác</option></select></div>
                    <div><span class="gym-label">Tên đăng nhập</span><p class="rounded-xl bg-slate-50 px-3 py-3 text-slate-600">{{ $customer->username }}</p></div>
                    <div><span class="gym-label">Email</span><p class="rounded-xl bg-slate-50 px-3 py-3 text-slate-600">{{ $customer->email }}</p></div>
                    <button class="gym-btn-primary sm:col-span-2 sm:justify-self-start" type="submit">Lưu thông tin</button>
                </form>
            </div>
        </div>
    </section>
@endsection

