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
    @if ((bool) $profile->REQUIRES_VERIFICATION)
        <section class="gym-card">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold">Xác minh ưu đãi {{ $profile->TENLOAI }}</h2>
                    @if ($tierVerification)
                        <p class="mt-2 text-sm text-emerald-800">Đã xác minh bởi {{ $tierVerification->verifier_name ?: 'quản lý' }} lúc {{ \Illuminate\Support\Carbon::parse($tierVerification->verified_at)->format('d/m/Y H:i') }}.</p>
                        <p class="mt-1 text-sm text-slate-600">Lý do: {{ $tierVerification->reason }}</p>
                    @else
                        <p class="mt-2 text-sm text-amber-800">Chưa xác minh. Ưu đãi chưa được áp dụng cho đơn mới.</p>
                    @endif
                    <p class="mt-2 text-xs text-slate-500">Kiểm tra trực tiếp giấy tờ tại quầy; không tải ảnh giấy tờ cá nhân lên website.</p>
                </div>
                <form class="flex flex-wrap items-end gap-3" method="POST" action="{{ route('gym.admin.customers.verify-tier', $profile->MAKH) }}">
                    @csrf
                    <input type="hidden" name="verified" value="{{ $tierVerification ? 0 : 1 }}">
                    <div><label class="gym-label" for="verification_reason">{{ $tierVerification ? 'Lý do thu hồi' : 'Lý do xác minh' }}</label><input class="gym-input" id="verification_reason" name="reason" maxlength="255" minlength="8" required placeholder="Ví dụ: Đã kiểm tra thẻ tại quầy"></div>
                    <button class="{{ $tierVerification ? 'gym-btn-outline border-red-600 text-red-700' : 'gym-btn-primary' }}" type="submit">{{ $tierVerification ? 'Thu hồi xác minh' : 'Xác minh hạng' }}</button>
                </form>
            </div>
        </section>
    @endif
    <section class="gym-card">
        <h2 class="text-xl font-bold">Lịch sử gói tập</h2>
        <div class="mt-4 overflow-x-auto"><table class="gym-table"><thead><tr><th>Gói</th><th>Ngày bắt đầu</th><th>Ngày tập cuối</th><th>Trạng thái</th><th>Quản lý</th></tr></thead><tbody>
        @forelse ($memberships as $membership)<tr><td>{{ $membership->TENGOI }}</td><td>{{ \Illuminate\Support\Carbon::parse($membership->NGAYBATDAU)->format('d/m/Y') }}</td><td>{{ \Illuminate\Support\Carbon::parse($membership->NGAYKETTHUC)->format('d/m/Y') }}</td><td>{{ $membership->TRANGTHAI }}</td><td>@include('gym.end-registration', ['membership' => $membership])</td></tr>@empty<tr><td colspan="5">Chưa có kỳ tập nào.</td></tr>@endforelse
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
