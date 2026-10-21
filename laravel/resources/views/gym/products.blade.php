@extends('layouts.app')
@section('title', 'Sản phẩm')
@section('content')
<section class="gym-container py-10">
    <header class="mb-8"><p class="text-sm font-semibold uppercase tracking-widest text-gym-blue">The Gym Store</p><h1 class="gym-title mt-2">Mọi thứ cho buổi tập của bạn</h1><p class="mt-3 text-slate-600">Thực phẩm bổ sung, quần áo, phụ kiện và dụng cụ tập luyện.</p></header>
    <div class="grid items-start gap-8 lg:grid-cols-[220px_minmax(0,1fr)]">
        <form class="gym-card space-y-4 p-5" method="GET" action="{{ route('gym.products.index') }}">
            <div><label class="gym-label" for="q">Tìm sản phẩm</label><input class="gym-input" id="q" name="q" value="{{ request('q') }}" placeholder="Tên sản phẩm"></div>
            <div><label class="gym-label" for="category">Nhóm sản phẩm</label><select class="gym-input" id="category" name="category"><option value="">Tất cả</option>@foreach ($categories as $category)<option value="{{ $category->MALOAISP }}" @selected(request('category') == $category->MALOAISP)>{{ $category->TENLOAISP }}</option>@endforeach</select></div>
            <div><label class="gym-label" for="brand">Hãng</label><select class="gym-input" id="brand" name="brand"><option value="">Tất cả</option>@foreach ($brands as $brand)<option @selected(request('brand') === $brand)>{{ $brand }}</option>@endforeach</select></div>
            <div><label class="gym-label" for="origin">Xuất xứ</label><select class="gym-input" id="origin" name="origin"><option value="">Tất cả</option>@foreach ($origins as $origin)<option @selected(request('origin') === $origin)>{{ $origin }}</option>@endforeach</select></div>
            <div><label class="gym-label" for="min">Giá từ (VNĐ)</label><input class="gym-input" id="min" type="number" min="0" name="min" value="{{ request('min') }}"></div>
            <div><label class="gym-label" for="max">Giá đến (VNĐ)</label><input class="gym-input" id="max" type="number" min="0" name="max" value="{{ request('max') }}"></div>
            <div><label class="gym-label" for="sort">Sắp xếp</label><select class="gym-input" id="sort" name="sort">@foreach (['name' => 'Tên A–Z', 'newest' => 'Mới nhất', 'price_up' => 'Giá tăng dần', 'price_down' => 'Giá giảm dần'] as $value => $label)<option value="{{ $value }}" @selected(request('sort', 'name') === $value)>{{ $label }}</option>@endforeach</select></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="stock" value="1" @checked(request('stock'))> Chỉ còn hàng</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="sale" value="1" @checked(request('sale'))> Đang khuyến mãi</label>
            <button class="gym-btn-primary w-full" type="submit">Lọc sản phẩm</button><a class="block text-center text-sm text-gym-blue underline" href="{{ route('gym.products.index') }}">Xóa bộ lọc</a>
        </form>
        <div class="min-w-0"><p class="mb-4 text-sm text-slate-500">{{ $products->total() }} sản phẩm</p><div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">@forelse ($products as $product)<x-product-card :product="$product" />@empty<p class="col-span-full py-10 text-slate-500">Không có sản phẩm phù hợp.</p>@endforelse</div><div class="mt-7">{{ $products->links() }}</div></div>
    </div>
</section>
@endsection
