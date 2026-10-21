@extends('layouts.app')

@section('title', 'Ảnh banner')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-8"><p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Nội dung trang chủ</p><h1 class="gym-title mt-2">Ảnh banner</h1><p class="mt-2 text-slate-600">Ảnh JPG, PNG hoặc WebP tối đa 3 MB. Banner tạm ẩn vẫn được giữ để bật lại.</p></header>
        <form class="gym-card mb-7 grid gap-4 sm:grid-cols-2" method="POST" action="{{ route('manager.banners.store') }}" enctype="multipart/form-data">
            @csrf
            <h2 class="text-lg font-bold sm:col-span-2">Thêm banner</h2>
            <div><label class="gym-label" for="title">Tên quản lý</label><x-form-input id="title" name="title" maxlength="160" required /></div>
            <div><label class="gym-label" for="alt_text">Mô tả ảnh cho trình đọc màn hình</label><x-form-input id="alt_text" name="alt_text" maxlength="255" required /></div>
            <div><label class="gym-label" for="image">Chọn ảnh</label><input class="gym-input" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" required aria-describedby="image-help"><p id="image-help" class="mt-1 text-xs text-slate-500">Tối đa 3 MB.</p></div>
            <div class="flex items-end"><button class="gym-btn-primary" type="submit">Tải lên banner</button></div>
        </form>
        <div class="grid gap-5">
            @forelse ($banners as $banner)
                <article class="gym-card grid gap-5 p-5 lg:grid-cols-[220px_1fr]">
                    <img class="aspect-video w-full rounded-xl object-cover" src="{{ asset('storage/'.$banner->image_path) }}" alt="{{ $banner->alt_text }}">
                    <div>
                        <form class="grid gap-4 sm:grid-cols-2" method="POST" action="{{ route('manager.banners.update', $banner) }}" enctype="multipart/form-data">
                            @csrf @method('PUT')
                            <div><label class="gym-label" for="title-{{ $banner->id }}">Tên quản lý</label><x-form-input id="title-{{ $banner->id }}" name="title" value="{{ $banner->title }}" maxlength="160" required /></div>
                            <div><label class="gym-label" for="alt-{{ $banner->id }}">Mô tả ảnh</label><x-form-input id="alt-{{ $banner->id }}" name="alt_text" value="{{ $banner->alt_text }}" maxlength="255" required /></div>
                            <div><label class="gym-label" for="image-{{ $banner->id }}">Thay ảnh (nếu cần)</label><input class="gym-input" id="image-{{ $banner->id }}" name="image" type="file" accept="image/jpeg,image/png,image/webp"></div>
                            <div class="flex items-end"><button class="gym-btn-outline" type="submit">Lưu nội dung</button></div>
                        </form>
                        <div class="mt-4 flex flex-wrap items-center gap-4 border-t border-slate-100 pt-4">
                            <form method="POST" action="{{ route('manager.banners.toggle', $banner) }}">@csrf @method('PATCH')<button class="font-semibold underline underline-offset-4 {{ $banner->is_active ? 'text-emerald-700' : 'text-slate-600' }}" type="submit">{{ $banner->is_active ? 'Đang bật · Bấm để ẩn' : 'Đang ẩn · Bấm để bật' }}</button></form>
                            <label class="text-sm text-slate-600">Thứ tự <input class="gym-input inline-block w-20" type="number" min="0" name="positions[{{ $banner->id }}]" value="{{ $banner->position }}" form="banner-order"></label>
                            <form class="ml-auto" method="POST" action="{{ route('manager.banners.destroy', $banner) }}" onsubmit="return confirm('Xóa banner này?')">@csrf @method('DELETE')<button class="font-semibold text-red-700 underline underline-offset-4" type="submit">Xóa banner</button></form>
                        </div>
                    </div>
                </article>
            @empty
                <p class="gym-card p-8 text-center text-slate-600">Chưa có banner. Trang chủ đang dùng ảnh mặc định.</p>
            @endforelse
        </div>
        @if ($banners->isNotEmpty())
            <form id="banner-order" class="mt-5 text-right" method="POST" action="{{ route('manager.banners.reorder') }}">@csrf @method('PATCH')<button class="gym-btn-primary" type="submit">Lưu thứ tự</button></form>
        @endif
    </section>
@endsection
