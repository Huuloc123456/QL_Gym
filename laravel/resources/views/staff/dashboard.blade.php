@extends('layouts.app')

@section('title', 'Vận hành')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-8">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Khu vực nhân viên</p>
            <h1 class="gym-title mt-2">Vận hành The Gym</h1>
            <p class="mt-2 text-slate-600">Mở nghiệp vụ bạn được giao để tiếp tục xử lý.</p>
        </header>

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            <a class="gym-card group block transition hover:-translate-y-0.5 hover:border-gym-blue hover:shadow-md" href="{{ route('staff.products.index') }}">
                <p class="text-sm font-bold uppercase tracking-wide text-gym-blue">Hàng hóa</p>
                <h2 class="mt-3 text-xl font-extrabold text-gym-ink">Quản lý sản phẩm</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">Cập nhật thông tin, ảnh và số lượng tồn thực tế.</p>
                <span class="mt-5 inline-block font-semibold text-gym-blue underline underline-offset-4">Mở sản phẩm →</span>
            </a>

            @can('viewAny', \App\Models\TrialRequest::class)
                <a class="gym-card group block transition hover:-translate-y-0.5 hover:border-gym-blue hover:shadow-md" href="{{ route('staff.trial-requests.index') }}">
                    <p class="text-sm font-bold uppercase tracking-wide text-gym-blue">Tiếp nhận khách hàng</p>
                    <h2 class="mt-3 text-xl font-extrabold text-gym-ink">Đăng ký tập thử</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Xem yêu cầu mới và cập nhật trạng thái liên hệ.</p>
                    <span class="mt-5 inline-block font-semibold text-gym-blue underline underline-offset-4">Mở danh sách →</span>
                </a>
            @endcan

            @can('viewAny', \App\Models\MembershipOrder::class)
                <a class="gym-card group block transition hover:-translate-y-0.5 hover:border-gym-blue hover:shadow-md" href="{{ route('gym.invoices.index') }}">
                    <p class="text-sm font-bold uppercase tracking-wide text-gym-blue">Đơn hàng</p>
                    <h2 class="mt-3 text-xl font-extrabold text-gym-ink">Đơn hàng và hóa đơn</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Tìm và xử lý đơn gói hội viên, hóa đơn sản phẩm và dịch vụ tại cùng một nơi.</p>
                    <span class="mt-5 inline-block font-semibold text-gym-blue underline underline-offset-4">Mở danh sách đơn →</span>
                </a>
            @endcan

            @can('viewAny', \App\Models\CheckIn::class)
                <a class="gym-card group block transition hover:-translate-y-0.5 hover:border-gym-blue hover:shadow-md" href="{{ route('staff.checkins.index') }}">
                    <p class="text-sm font-bold uppercase tracking-wide text-gym-blue">Tại phòng tập</p>
                    <h2 class="mt-3 text-xl font-extrabold text-gym-ink">Check-in hội viên</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Tra cứu quyền tập và quản lý lượt vào, ra.</p>
                    <span class="mt-5 inline-block font-semibold text-gym-blue underline underline-offset-4">Mở check-in →</span>
                </a>
            @endcan
        </div>

        @unless (auth()->user()->canManageTrialRequests() || auth()->user()->canManageOrders() || auth()->user()->canManageCheckIns())
            <div class="gym-card mt-5 border-amber-200 bg-amber-50 p-6 text-amber-950" role="status">
                <h2 class="text-lg font-bold">Chưa được cấp quyền nghiệp vụ</h2>
                <p class="mt-1 leading-6">Tài khoản của bạn chưa được giao phần việc nào. Vui lòng liên hệ quản lý để được cấp quyền.</p>
            </div>
        @endunless
    </section>
@endsection
