<nav class="fixed inset-x-0 top-0 z-40 bg-slate-800 text-white lg:bottom-0 lg:right-auto lg:w-[260px] lg:overflow-y-auto" aria-label="Điều hướng chính">
    <div class="flex min-h-20 items-center justify-between px-6 lg:pt-5">
        <a class="flex items-center gap-3" href="{{ route('home') }}" aria-label="The Gym - trang chủ">
            <img class="h-12 w-[68px] rounded-md bg-white object-contain p-1" src="{{ asset('images/csharp/logo.png') }}" alt="The Gym" width="68" height="46">
            <span class="text-lg font-bold">{{ auth()->user()->role === 'manager' ? 'Quản lý' : 'Nhân viên' }}</span>
        </a>
        <button class="grid size-11 place-items-center rounded-lg border border-slate-500 lg:hidden" type="button" data-menu-toggle aria-controls="gymNavbar" aria-expanded="false" aria-label="Mở menu">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="size-6 stroke-current" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/></svg>
        </button>
    </div>
    <div class="hidden max-h-[calc(100svh-5rem)] overflow-y-auto px-4 pb-6 lg:flex lg:max-h-none lg:flex-1 lg:flex-col" id="gymNavbar" data-menu>
        @if (auth()->user()->role === 'manager')
            <a class="gym-internal-link mt-4" href="{{ route('manager.payment-reviews.index') }}">Đối soát thanh toán</a>
            <a class="gym-internal-link" href="{{ route('manager.banners.index') }}">Ảnh banner</a>
            <a class="gym-internal-link" href="{{ route('manager.knowledge.index') }}">Tài liệu chatbot</a>
            <a class="gym-internal-link mt-4" href="{{ route('manager.dashboard') }}" @if (request()->routeIs('manager.dashboard')) aria-current="page" @endif>Tổng quan quản lý</a>
        @endif
        <a class="gym-internal-link mt-3" href="{{ route('gym.messages') }}">Thông báo @if($unreadMessages)<span class="ml-2 rounded-full bg-sky-500 px-2 py-1 text-xs">{{ $unreadMessages }}</span>@endif</a><a class="gym-internal-link" href="{{ route('gym.security') }}">Bảo mật tài khoản</a><div class="mt-5" data-nav-group="operations">
            <p class="px-4 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Vận hành</p>
            <a class="gym-internal-link" href="{{ route('staff.dashboard') }}" @if (request()->routeIs('staff.dashboard')) aria-current="page" @endif>Tổng quan vận hành</a>
            <a class="gym-internal-link" href="{{ route('staff.products.index') }}" @if (request()->routeIs('staff.products.*')) aria-current="page" @endif>Quản lý sản phẩm</a>
            @can('viewAny', \App\Models\TrialRequest::class)
                <a class="gym-internal-link" href="{{ route('staff.trial-requests.index') }}" @if (request()->routeIs('staff.trial-requests.*')) aria-current="page" @endif>Đăng ký tập thử</a>
            @endcan
            @can('viewAny', \App\Models\CheckIn::class)
                <a class="gym-internal-link" href="{{ route('staff.checkins.index') }}" @if (request()->routeIs('staff.checkins.*')) aria-current="page" @endif>Check-in</a>
            @endcan
            @can('viewAny', \App\Models\MembershipOrder::class)
                <a class="gym-internal-link" href="{{ route('gym.invoices.index') }}" @if (request()->routeIs('gym.invoices.*')) aria-current="page" @endif>Đơn hàng và hóa đơn</a>
            @endcan
            <a class="gym-internal-link" href="{{ route('gym.calendar') }}" @if (request()->routeIs('gym.calendar')) aria-current="page" @endif>Lịch lớp học</a>
        </div>
        @if (auth()->user()->role === 'manager')
            <a class="gym-internal-link mt-4" href="{{ route('manager.payment-reviews.index') }}" @if (request()->routeIs('manager.payment-reviews.*')) aria-current="page" @endif>Đối soát VNPay</a><a class="gym-internal-link" href="{{ route('manager.reports.index') }}" @if (request()->routeIs('manager.reports.*')) aria-current="page" @endif>Báo cáo hoạt động</a><a class="gym-internal-link" href="{{ route('manager.settings.edit') }}" @if (request()->routeIs('manager.settings.*')) aria-current="page" @endif>Cấu hình phòng gym</a><div class="mt-5" data-nav-group="management">
                <p class="px-4 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Quản lý</p>
                @foreach (['products' => 'Sản phẩm và tồn kho', 'classes' => 'Lớp học', 'customers' => 'Khách hàng', 'employees' => 'Nhân sự và chuyên môn', 'positions' => 'Chức vụ', 'tiers' => 'Hạng khách hàng', 'specialties' => 'Chuyên môn', 'categories' => 'Nhóm sản phẩm'] as $resource => $label)
                    <a class="gym-internal-link" href="{{ route('gym.admin.index', $resource) }}" @if (request()->route('resource') === $resource) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
                <a class="gym-internal-link" href="{{ route('manager.packages.index') }}" @if (request()->routeIs('manager.packages.*')) aria-current="page" @endif>Quản lý gói tập</a>
                <a class="gym-internal-link" href="{{ route('manager.employees.index') }}" @if (request()->routeIs('manager.employees.*')) aria-current="page" @endif>Tài khoản nhân viên</a>
                <a class="gym-internal-link" href="{{ route('gym.database.index') }}">Quản trị CSDL và nhật ký</a>
            </div>
        @endif
        <div class="mt-8 border-t border-slate-700 pt-4">
            <a class="gym-internal-link" href="{{ route('home') }}">Về trang khách hàng ↗</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="gym-internal-link w-full text-left" type="submit">Đăng xuất</button></form>
        </div>
    </div>
</nav>
