@extends('layouts.app')
@section('title', 'Đơn hàng và hóa đơn')
@section('content')
<section class="gym-container py-10">
    <h1 class="gym-title">Đơn hàng và hóa đơn</h1>
    <p class="mt-2 text-slate-600">Đơn gói hội viên và đơn sản phẩm được tra cứu tại đây. Hóa đơn dịch vụ cũ vẫn được lưu để xem lịch sử và đối chiếu.</p>

    <form class="mt-6 flex flex-wrap items-end gap-3" method="GET">
        <div><label class="gym-label" for="q">Tìm mã đơn, tên khách hoặc gói</label><input class="gym-input" id="q" name="q" value="{{ request('q') }}"></div>
        <div><label class="gym-label" for="status">Thanh toán</label><select class="gym-input" id="status" name="status"><option value="">Tất cả</option>@foreach (['Chưa thanh toán', 'Đã thanh toán', 'Hủy'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
        <div><label class="gym-label" for="source">Loại đơn</label><select class="gym-input" id="source" name="source"><option value="">Tất cả</option><option value="invoice" @selected(request('source') === 'invoice')>Có hóa đơn</option><option value="package_order" @selected(request('source') === 'package_order')>Gói đang xử lý</option></select></div>
        <div><label class="gym-label" for="mode">Hình thức nhận</label><select class="gym-input" id="mode" name="mode"><option value="">Tất cả</option><option value="pickup" @selected(request('mode') === 'pickup')>Tại quầy / dịch vụ</option><option value="delivery" @selected(request('mode') === 'delivery')>Giao hàng</option></select></div>
        <button class="gym-btn-primary" type="submit">Lọc</button>
    </form>

    <p class="mt-5 text-sm text-slate-600">{{ number_format($invoices->total()) }} đơn · {{ $invoices->firstItem() ?? 0 }}–{{ $invoices->lastItem() ?? 0 }}</p>
    <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white" tabindex="0" aria-label="Bảng đơn hàng và hóa đơn, vuốt ngang để xem thêm">
        <table class="gym-table">
            <thead><tr><th>Mã</th><th>Khách hàng / nội dung</th><th>Ngày đặt</th><th>Thành tiền</th><th>Thanh toán</th><th>Giao nhận</th><th></th></tr></thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    @php($isPackageOrder = $invoice->source === 'package_order')
                    <tr>
                        <td class="whitespace-nowrap">{{ $isPackageOrder ? 'Gói #'.$invoice->record_id : 'HĐ #'.$invoice->record_id }}</td>
                        <td>{{ $invoice->customer_name }} @if ($invoice->item_name)<span class="block text-xs text-slate-500">{{ $invoice->item_name }}</span>@endif</td>
                        <td class="whitespace-nowrap">{{ \Carbon\Carbon::parse($invoice->ordered_at)->format('d/m/Y H:i') }}</td>
                        <td class="whitespace-nowrap font-semibold">{{ number_format($invoice->amount, 0, ',', '.') }}đ</td>
                        <td>{{ $invoice->status }} @if ($invoice->cancellation_requested)<span class="block text-xs text-amber-700">Khách yêu cầu hủy</span>@endif</td>
                        <td>{{ $isPackageOrder ? 'Không giao hàng' : (['pending' => 'Chờ chuẩn bị', 'ready' => 'Sẵn sàng nhận', 'collected' => 'Đã nhận', 'shipping' => 'Đang giao', 'delivered' => 'Đã giao', 'cancelled' => 'Đã hủy', 'expired' => 'Hết hạn thanh toán'][$invoice->state] ?? 'Hóa đơn lịch sử') }}</td>
                        <td>
                            @if ($isPackageOrder && auth()->user()->canManageOrders() && $invoice->membershipOrder)
                                <a class="whitespace-nowrap font-semibold text-gym-blue underline underline-offset-4 hover:text-sky-800" href="{{ route('staff.orders.show', $invoice->record_id) }}">Chi tiết</a>
                            @else
                                <a class="font-semibold text-gym-blue underline" href="{{ $isPackageOrder ? route('my.orders.show', $invoice->record_id) : route('gym.invoices.show', $invoice->record_id) }}">Chi tiết</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">Chưa có đơn hàng phù hợp.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $invoices->links() }}</div>
</section>
@endsection
