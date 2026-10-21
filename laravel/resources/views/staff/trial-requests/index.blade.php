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
                <a class="gym-btn-outline" href="{{ route('staff.orders.index') }}">Đơn hội viên</a>
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
                    <option value="received" @selected(($filters['status'] ?? '') === 'received')>Chưa liên hệ</option>
                    <option value="contacted" @selected(($filters['status'] ?? '') === 'contacted')>Đã liên hệ</option>
                </x-form-select>
            </div>
            <button class="gym-btn-primary" type="submit">Lọc danh sách</button>
        </form>

        <div class="grid gap-4">
            @forelse ($trialRequests as $trialRequest)
                <article class="gym-card p-5 sm:p-6">
                    @if ($trialRequest->sql_membership_id)<p class="mb-4 text-sm font-semibold text-emerald-700">Đã duyệt tập thử · Mã đăng ký {{ $trialRequest->sql_membership_id }}</p>@else<form class="mb-5 flex flex-wrap items-end gap-3 border-b border-slate-200 pb-5" method="POST" action="{{ route('staff.trial-requests.approve', $trialRequest) }}">@csrf<div><label class="gym-label" for="trial-package-{{ $trialRequest->id }}">Thời lượng thử (tối đa 7 ngày)</label><select class="gym-input" id="trial-package-{{ $trialRequest->id }}" name="package" required>@foreach ($trialPackages as $package)<option value="{{ $package->MAGOITAP }}">{{ $package->TENGOI }} · thử {{ $package->THOIHAN }} ngày</option>@endforeach</select></div><label class="flex max-w-xs items-center gap-2 text-sm"><input type="checkbox" name="identity_verified" value="1" required> Đã xác minh khách và số điện thoại</label><button class="gym-btn-outline" type="submit">Duyệt tập thử</button></form>@endif
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="break-words text-lg font-bold">{{ $trialRequest->name }}</h2>
                                @if ($trialRequest->status === 'received')
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
                        @if ($trialRequest->status === 'received')
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
