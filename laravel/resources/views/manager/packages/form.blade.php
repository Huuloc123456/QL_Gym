@extends('layouts.app')

@section('title', $isEditing ? 'Sửa thông tin gói' : 'Tạo gói tập mới')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <a class="font-semibold text-gym-blue underline underline-offset-4" href="{{ route('manager.packages.index') }}">← Quản lý gói tập</a>
        <article class="gym-card mx-auto mt-5 max-w-3xl p-6 sm:p-9">
            <h1 class="gym-title">{{ $isEditing ? 'Sửa thông tin gói' : 'Tạo gói tập mới' }}</h1>
            <form class="mt-6 space-y-5" method="POST" action="{{ $isEditing ? route('manager.packages.update', $package) : route('manager.packages.store') }}">
                @csrf
                @if ($isEditing)
                    @method('PUT')
                @endif
                <div><label class="gym-label" for="package-name">Tên gói tập</label><x-form-input id="package-name" name="name" type="text" maxlength="100" value="{{ old('name', $package->name) }}" required /></div>
                <div><label class="gym-label" for="package-description">Mô tả (không bắt buộc)</label><x-form-textarea id="package-description" name="description" rows="4" maxlength="5000">{{ old('description', $package->description) }}</x-form-textarea></div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div><label class="gym-label" for="package-price">Giá (VNĐ)</label><x-form-input id="package-price" name="price" type="number" min="1" max="4294967295" step="1" value="{{ old('price', $package->price) }}" required /></div>
                    <div><label class="gym-label" for="package-duration">Thời hạn (tháng)</label><x-form-input id="package-duration" name="duration_months" type="number" min="1" max="65535" step="1" value="{{ old('duration_months', $package->duration_months) }}" required /></div>
                </div>
                @unless ($isEditing)
                    <p class="text-sm text-slate-600">Gói mới sẽ được mở bán sau khi lưu.</p>
                @endunless
                <div class="flex flex-wrap gap-3"><button class="gym-btn-primary" type="submit">{{ $isEditing ? 'Lưu thay đổi' : 'Tạo gói tập' }}</button><a class="gym-btn-outline" href="{{ route('manager.packages.index') }}">Hủy</a></div>
            </form>
        </article>
    </section>
@endsection
