@extends('layouts.app')
@section('title', 'Hồ sơ khách hàng · '.$profile->TENKH)
@section('content')
<section class="gym-container space-y-7 py-10">
    <a class="font-semibold text-gym-blue underline" href="{{ route('gym.admin.index', 'customers') }}">← Danh sách khách hàng</a>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-bold uppercase tracking-[0.16em] text-gym-blue">Hồ sơ khách hàng · #{{ $profile->MAKH }}</p>
            <h1 class="gym-title mt-2">{{ $profile->TENKH }}</h1>
            <p class="mt-2 text-slate-600">{{ $profile->EMAIL ?: 'Chưa có email' }} · {{ $profile->SDT ?: 'Chưa có số điện thoại' }}</p>
        </div>
        <a class="gym-btn-outline" href="{{ route('gym.admin.edit', ['customers', $profile->MAKH]) }}">Chỉnh sửa hồ sơ</a>
    </header>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <article class="gym-card"><p class="text-sm text-slate-500">Hạng hội viên</p><p class="mt-2 text-lg font-bold">{{ $profile->TENLOAI ?: 'Chưa xếp hạng' }}</p></article>
        <article class="gym-card"><p class="text-sm text-slate-500">Tài khoản web</p><p class="mt-2 text-lg font-bold">{{ $account ? ($account->is_active ? 'Đang hoạt động' : 'Đã khóa') : 'Chưa liên kết' }}</p></article>
        <article class="gym-card"><p class="text-sm text-slate-500">Tổng đơn</p><p class="mt-2 text-lg font-bold">{{ $invoices->total() }}</p></article>
        <article class="gym-card"><p class="text-sm text-slate-500">Lượt check-in</p><p class="mt-2 text-lg font-bold">{{ $checkIns->total() }}</p></article>
    </div>
    <section class="gym-card">
        <h2 class="text-xl font-bold">Lịch sử gói tập</h2>
        <div class="mt-4 overflow-x-auto"><table class="gym-table"><thead><tr><th>Gói</th><th>Ngày bắt đầu</th><th>Ngày tập cuối</th><th>Trạng thái</th></tr></thead><tbody>
        @forelse ($memberships as $membership)<tr><td>{{ $membership->TENGOI }}</td><td>{{ \Illuminate\Support\Carbon::parse($membership->NGAYBATDAU)->format('d/m/Y') }}</td><td>{{ \Illuminate\Support\Carbon::parse($membership->NGAYKETTHUC)->format('d/m/Y') }}</td><td>{{ $membership->TRANGTHAI }}</td></tr>@empty<tr><td colspan="4">Chưa có kỳ tập nào.</td></tr>@endforelse
        </tbody></table></div><div class="mt-4">{{ $memberships->links() }}</div>
    </section>
    <section class="gym-card">
        <h2 class="text-xl font-bold">Đơn hàng và thanh toán</h2>
        <div class="mt-4 overflow-x-auto"><table class="gym-table"><thead><tr><th>Mã đơn</th><th>Ngày lập</th><th>Thành tiền</th><th>Trạng thái</th></tr></thead><tbody>
        @forelse ($invoices as $invoice)<tr><td>{{ $invoice->source === 'invoice' ? 'HD-'.$invoice->record_id : 'Đơn gói #'.$invoice->record_id }}<span class="block text-xs text-slate-500">{{ $invoice->item_name }}</span></td><td>{{ \Illuminate\Support\Carbon::parse($invoice->ordered_at)->format('d/m/Y H:i') }}</td><td>{{ number_format((int) $invoice->amount, 0, ',', '.') }}đ</td><td>{{ $invoice->status }}</td></tr>@empty<tr><td colspan="4">Chưa có đơn hàng.</td></tr>@endforelse
        </tbody></table></div><div class="mt-4">{{ $invoices->links() }}</div>
    </section>
    <section class="gym-card">
        <h2 class="text-xl font-bold">Lịch sử check-in</h2>
        <div class="mt-4 overflow-x-auto"><table class="gym-table"><thead><tr><th>Thời gian vào</th><th>Thời gian ra</th><th>Trạng thái</th></tr></thead><tbody>
        @forelse ($checkIns as $checkIn)<tr><td>{{ \Illuminate\Support\Carbon::parse($checkIn->THOIGIANVAO)->format('d/m/Y H:i') }}</td><td>{{ $checkIn->THOIGIANRA ? \Illuminate\Support\Carbon::parse($checkIn->THOIGIANRA)->format('d/m/Y H:i') : 'Chưa ghi nhận' }}</td><td>{{ $checkIn->TRANGTHAI }}</td></tr>@empty<tr><td colspan="3">Chưa có lượt tập.</td></tr>@endforelse
        </tbody></table></div><div class="mt-4">{{ $checkIns->links() }}</div>
    </section>
</section>
@endsection
