@extends('layouts.app')
@section('title', $product->TENSP)
@section('content')
<section class="gym-container py-10">
    <a class="text-sm text-gym-blue underline" href="{{ route('gym.products.index') }}">← Sản phẩm</a>
    <div class="mt-6 grid gap-8 md:grid-cols-2">
        <div><img class="aspect-square w-full rounded-xl border border-slate-200 bg-slate-50 object-contain p-6" src="{{ \App\Services\OriginalGymSql::image($images->first()?->URL, (int) $product->MASP) }}" alt="{{ $product->TENSP }}" width="600" height="600"><div class="mt-3 flex flex-wrap gap-2">@foreach ($images as $image)<a href="{{ \App\Services\OriginalGymSql::image($image->URL, (int) $product->MASP) }}" target="_blank" rel="noopener" aria-label="Xem ảnh {{ $loop->iteration }} của {{ $product->TENSP }}"><img class="size-20 rounded border border-slate-200 object-contain" src="{{ \App\Services\OriginalGymSql::image($image->URL, (int) $product->MASP) }}" alt="Ảnh {{ $loop->iteration }}" width="80" height="80" loading="lazy"></a>@endforeach</div></div>
        <div><p class="text-sm text-slate-500">{{ $product->HANG }}</p><h1 class="gym-title mt-2 text-2xl sm:text-3xl">{{ $product->TENSP }}</h1><p class="mt-6 text-3xl font-bold text-gym-blue">{{ number_format($product->unit_price, 0, ',', '.') }} VNĐ</p>@if ($product->unit_price < $product->DONGIA)<del class="text-slate-400">{{ number_format($product->DONGIA, 0, ',', '.') }} VNĐ</del>@endif
            <dl class="mt-5 grid grid-cols-[auto_1fr] gap-x-5 gap-y-3 text-sm"><dt class="text-slate-500">Xuất xứ</dt><dd>{{ $product->XUATXU ?: 'Chưa cập nhật' }}</dd><dt class="text-slate-500">Bảo hành</dt><dd>{{ $product->BAOHANH ?: 'Không áp dụng' }}</dd><dt class="text-slate-500">Có thể đặt</dt><dd>{{ $available }} sản phẩm</dd></dl>
            <p class="mt-6 whitespace-pre-line leading-7 text-slate-700">{{ $product->MOTACHUNG }}</p>
            @if ($available > 0)
                @if (auth()->user()?->role === 'customer')<form class="mt-6 flex flex-wrap items-end gap-3" method="POST" action="{{ route('gym.cart.add') }}">@csrf<input type="hidden" name="kind" value="product"><input type="hidden" name="item" value="{{ $product->MASP }}"><div><label class="gym-label" for="quantity">Số lượng</label><input class="gym-input w-24" id="quantity" type="number" name="quantity" min="1" max="{{ min($available, 99) }}" value="1" required></div><button class="gym-btn-primary" type="submit">Thêm vào giỏ hàng</button></form>
                @else<a class="gym-btn-primary mt-6" href="{{ route('login') }}">Đăng nhập để mua hàng</a>@endif
            @else<p class="mt-6 rounded-lg bg-slate-100 p-4 font-semibold">Sản phẩm hiện hết hàng.</p>@endif
            <p class="mt-5 text-sm text-slate-500">Nhận tại quầy hoặc giao theo địa chỉ. Phí giao hiển thị ở bước đặt hàng; nhân viên xác nhận khoản thu.</p>
        </div>
    </div>
    <section class="mt-10 border-t border-slate-200 pt-7"><h2 class="text-xl font-bold">Mô tả chi tiết</h2><p class="mt-3 whitespace-pre-line leading-8 text-slate-700">{{ $product->MOTACHITIET ?: 'Chưa cập nhật thông tin chi tiết.' }}</p></section>
</section>
@endsection
