@extends('layouts.app')

@section('title', 'Đăng ký tập thử')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Khu vực nhân viên</p>
                <h1 class="gym-title mt-2">Đăng ký tập thử</h1>
                <p class="mt-2 text-slate-600">Liên hệ khách đã để lại thông tin và lưu trạng thái xử lý.</p>
            </div>
            @can('viewAny', \App\Models\MembershipOrder::class)
                <a class="gym-btn-outline" href="{{ route('gym.invoices.index', ['source' => 'package_order']) }}">Đơn hội viên</a>
            @endcan
        </header>

        <form class="gym-card mb-6 grid gap-4 sm:grid-cols-[1fr_auto_auto] sm:items-end" method="GET" action="{{ route('staff.trial-requests.index') }}">
            <div>
                <label class="gym-label" for="search">Tìm tên, số điện thoại hoặc email</label>
                <x-form-input id="search" name="search" value="{{ $filters['search'] ?? '' }}" autocomplete="off" />
            </div>
            <div>
                <label class="gym-label" for="status">Trạng thái</label>
                <x-form-select class="sm:min-w-48" id="status" name="status">
                    <option value="">Tất cả yêu cầu</option>
                    <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>Chờ duyệt</option>
                    <option value="approved" @selected(($filters['status'] ?? '') === 'approved')>Đã duyệt</option>
                    <option value="rejected" @selected(($filters['status'] ?? '') === 'rejected')>Đã từ chối</option>
                    <option value="received" @selected(($filters['status'] ?? '') === 'received')>Chưa liên hệ</option>
                    <option value="contacted" @selected(($filters['status'] ?? '') === 'contacted')>Đã liên hệ</option>
                </x-form-select>
            </div>
            <button class="gym-btn-primary" type="submit">Lọc danh sách</button>
        </form>

        <div class="grid gap-4">
            @forelse ($trialRequests as $trialRequest)
                <article class="gym-card p-5 sm:p-6">
                    @if ($trialRequest->status === 'pending' && $trialRequest->user_id)
                        <div class="mb-5 grid gap-4 border-b border-slate-200 pb-5 lg:grid-cols-2">
                            <form class="space-y-3" method="POST" action="{{ route('staff.trial-requests.approve', $trialRequest) }}">
                                @csrf
                                <div><label class="gym-label" for="trial-date-{{ $trialRequest->id }}">Ngày bắt đầu</label><input class="gym-input" id="trial-date-{{ $trialRequest->id }}" name="start_date" type="date" min="{{ now()->toDateString() }}" value="{{ max($trialRequest->desired_date?->toDateString() ?? '', now()->toDateString()) }}" required></div>
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="identity_verified" value="1" required> Đã xác minh khách và số điện thoại</label>
                                <button class="gym-btn-primary" type="submit">Duyệt tập thử</button>
                            </form>
                            <form class="space-y-3" method="POST" action="{{ route('staff.trial-requests.reject', $trialRequest) }}">
                                @csrf
                                <div><label class="gym-label" for="trial-reason-{{ $trialRequest->id }}">Lý do từ chối</label><input class="gym-input" id="trial-reason-{{ $trialRequest->id }}" name="reason" minlength="5" maxlength="1000" required></div>
                                <button class="gym-btn-outline" type="submit">Từ chối yêu cầu</button>
                            </form>
                        </div>
                    @elseif ($trialRequest->sql_membership_id)
                        <p class="mb-4 text-sm font-semibold text-emerald-700">Đã duyệt trước đây · Mã đăng ký {{ $trialRequest->sql_membership_id }}</p>
                    @elseif (! $trialRequest->user_id)
                        <p class="mb-4 text-sm text-amber-800">Yêu cầu cũ chưa gắn tài khoản: cần quản lý đối chiếu trước khi cấp quyền.</p>
                        @if (auth()->user()->role === 'manager' && ! $trialRequest->sql_membership_id && in_array($trialRequest->status, ['received', 'contacted'], true))
                            <form class="mb-5 grid gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4 sm:grid-cols-2" method="POST" action="{{ route('staff.trial-requests.link-account', $trialRequest) }}">
                                @csrf
                                <p class="text-sm leading-6 text-amber-950 sm:col-span-2">Chỉ gắn khi đã đối chiếu trực tiếp tài khoản và số điện thoại với khách. Hệ thống không tự dò hoặc chọn tài khoản.</p>
                                <div><label class="gym-label" for="legacy-username-{{ $trialRequest->id }}">Tên đăng nhập hội viên</label><x-form-input id="legacy-username-{{ $trialRequest->id }}" name="username" maxlength="60" required /></div>
                                <div><label class="gym-label" for="legacy-phone-{{ $trialRequest->id }}">Số điện thoại trên yêu cầu cũ</label><x-form-input id="legacy-phone-{{ $trialRequest->id }}" name="phone_confirmed" value="{{ $trialRequest->phone }}" maxlength="20" required /></div>
                                <label class="flex items-start gap-2 text-sm sm:col-span-2"><input class="mt-1" type="checkbox" name="identity_verified" value="1" required> Tôi đã đối chiếu danh tính và số điện thoại với khách.</label>
                                <button class="gym-btn-outline sm:col-span-2 sm:justify-self-start" type="submit">Đối chiếu và gắn tài khoản</button>
                            </form>
                        @endif
                    @elseif ($trialRequest->status === 'approved' && $trialRequest->entitlement)
                        @if ($trialRequest->entitlement->revoked_at)
                            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-950">
                                <p class="font-semibold">Đã thu hồi {{ $trialRequest->entitlement->revoked_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · {{ $trialRequest->entitlement->revokedBy?->name ?? 'Người xử lý đã ngừng hoạt động' }}</p>
                                <p class="mt-1">Lý do: {{ $trialRequest->entitlement->revocation_reason }}</p>
                            </div>
                        @else
                            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950">
                                <p class="font-semibold">Lịch tập thử: {{ $trialRequest->entitlement->starts_at->format('d/m/Y') }} đến hết {{ $trialRequest->entitlement->ends_at->copy()->subSecond()->format('d/m/Y') }}</p>
                                @if ($trialRequest->entitlement->check_ins_count === 0)
                                    <form class="mt-4 flex flex-wrap items-end gap-3" method="POST" action="{{ route('staff.trial-requests.reschedule', $trialRequest) }}">
                                        @csrf
                                        <div><label class="gym-label" for="reschedule-trial-{{ $trialRequest->id }}">Đổi ngày bắt đầu</label><input class="gym-input" id="reschedule-trial-{{ $trialRequest->id }}" name="start_date" type="date" min="{{ now()->toDateString() }}" value="{{ $trialRequest->entitlement->starts_at->toDateString() }}" required></div>
                                        <button class="gym-btn-outline" type="submit">Lưu lịch mới</button>
                                    </form>
                                @else
                                    <p class="mt-2">Khách đã có lượt vào tập nên không thể đổi lịch.</p>
                                @endif
                                <form class="mt-4 grid gap-3 border-t border-emerald-200 pt-4 sm:grid-cols-[1fr_auto] sm:items-end" method="POST" action="{{ route('staff.trial-requests.revoke', $trialRequest) }}">
                                    @csrf
                                    <div><label class="gym-label" for="revoke-trial-{{ $trialRequest->id }}">Lý do thu hồi</label><x-form-input id="revoke-trial-{{ $trialRequest->id }}" name="reason" minlength="5" maxlength="1000" required /></div>
                                    <button class="gym-btn-outline border-red-300 text-red-800 hover:bg-red-50" type="submit">Thu hồi quyền</button>
                                </form>
                            </div>
                        @endif
                    @endif
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="break-words text-lg font-bold">{{ $trialRequest->name }}</h2>
                                @if ($trialRequest->entitlement?->revoked_at)
                                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-900">Đã thu hồi</span>
                                @elseif ($trialRequest->status === 'pending')
                                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-950">Chờ duyệt</span>
                                @elseif ($trialRequest->status === 'approved')
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-900">Đã duyệt</span>
                                @elseif ($trialRequest->status === 'rejected')
                                    <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-900">Đã từ chối</span>
                                @elseif ($trialRequest->status === 'cancelled')
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">Đã hủy</span>
                                @elseif ($trialRequest->status === 'received')
                                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-950">Chưa liên hệ</span>
                                @else
                                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-950">Đã liên hệ</span>
                                @endif
                            </div>
                            <p class="mt-2 text-sm text-slate-600">Gửi lúc {{ $trialRequest->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
                            <dl class="mt-4 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                                <div><dt class="text-slate-500">Điện thoại</dt><dd class="mt-0.5 font-semibold"><a class="underline underline-offset-2" href="tel:{{ $trialRequest->phone }}">{{ $trialRequest->phone }}</a></dd></div>
                                <div><dt class="text-slate-500">Email</dt><dd class="mt-0.5 break-all font-semibold">{{ $trialRequest->email ?: 'Chưa cung cấp' }}</dd></div>
                            </dl>
                            @if ($trialRequest->handled_at)
                                <p class="mt-3 text-sm text-slate-600">Đã liên hệ {{ $trialRequest->handled_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · {{ $trialRequest->handler?->name ?? 'Nhân viên đã ngừng hoạt động' }}</p>
                            @endif
                        </div>
                        @if (in_array($trialRequest->status, ['received', 'pending'], true) && ! $trialRequest->handled_at)
                            <form class="shrink-0" method="POST" action="{{ route('staff.trial-requests.contact', $trialRequest) }}">
                                @csrf
                                <button class="gym-btn-primary w-full sm:w-auto" type="submit">Đánh dấu đã liên hệ</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="gym-card py-10 text-center text-slate-600">
                    <h2 class="font-bold">Không có yêu cầu phù hợp</h2>
                    <p class="mt-1 text-sm">Thử đổi bộ lọc hoặc tìm kiếm theo số điện thoại.</p>
                </div>
            @endforelse
        </div>

        @if ($trialRequests->hasPages())
            <nav class="mt-6" aria-label="Các trang đăng ký tập thử">{{ $trialRequests->links() }}</nav>
        @endif
    </section>
@endsection
