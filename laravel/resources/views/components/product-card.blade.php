@props(['product'])
@php($price = $product->GIAKHUYENMAI !== null ? min((int) $product->DONGIA, (int) $product->GIAKHUYENMAI) : (int) $product->DONGIA)
<article class="flex min-w-0 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white">
    <a class="group relative block bg-slate-50" href="{{ route('gym.products.show', $product->MASP) }}">
        <img class="aspect-square w-full object-contain p-3 transition-transform group-hover:scale-105" src="{{ \App\Services\OriginalGymSql::image($product->image ?? null, (int) $product->MASP) }}" alt="{{ $product->TENSP }}" width="400" height="400" loading="lazy">
        @if ($product->XUATXU)<span class="absolute left-3 top-3 rounded bg-white/95 px-2 py-1 text-xs font-semibold text-slate-700">{{ $product->XUATXU }}</span>@endif
    </a>
    <div class="flex flex-1 flex-col p-4">
        <p class="text-xs text-slate-500">{{ $product->HANG ?: 'The Gym' }}</p>
        <h3 class="mt-1 text-sm font-semibold leading-6"><a class="hover:text-gym-blue hover:underline" href="{{ route('gym.products.show', $product->MASP) }}">{{ $product->TENSP }}</a></h3>
        <div class="mt-auto pt-3"><strong class="text-gym-blue">{{ number_format($price, 0, ',', '.') }}đ</strong>@if ($price < $product->DONGIA)<del class="ml-2 text-xs text-slate-400">{{ number_format($product->DONGIA, 0, ',', '.') }}đ</del>@endif</div>
        @php($available = $product->available_quantity ?? $product->SOLUONGTON)
        <p class="mt-2 text-xs {{ $available > 0 ? 'text-emerald-700' : 'text-slate-500' }}">{{ $available > 0 ? 'Còn hàng' : 'Tạm hết hàng có thể đặt' }}</p>
    </div>
</article>
