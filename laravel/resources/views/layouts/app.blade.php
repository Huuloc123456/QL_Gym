<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f5f8fa">
    <title>@yield('title', 'The Gym') - The Gym</title>
    @include('components.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="sr-only fixed left-4 top-4 z-50 rounded-lg bg-white px-4 py-3 text-gym-ink shadow-lg focus:not-sr-only focus:outline-2 focus:outline-gym-blue" href="#main">Bỏ qua điều hướng</a>

    <nav class="fixed inset-x-0 top-0 z-40 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur" aria-label="Điều hướng chính">
        <div class="gym-container flex min-h-20 items-center justify-between gap-4 py-3">
            <a class="shrink-0" href="{{ route('home') }}" aria-label="The Gym - trang chủ">
                <img src="{{ asset('images/csharp/logo.png') }}" alt="The Gym" width="96" height="64">
            </a>
            <button class="inline-flex size-11 items-center justify-center rounded-lg border border-slate-300 text-gym-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gym-blue lg:hidden" type="button" data-menu-toggle aria-controls="gymNavbar" aria-expanded="false" aria-label="Mở menu">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="size-6 stroke-current" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/></svg>
            </button>
            <div class="absolute left-0 right-0 top-full hidden border-b border-slate-200 bg-white px-4 pb-4 shadow-lg lg:static lg:flex lg:min-w-0 lg:flex-1 lg:items-center lg:justify-between lg:gap-2 lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none" id="gymNavbar" data-menu>
                <ul class="flex flex-col gap-1 lg:flex-row lg:items-center lg:gap-1">
                    <li><a class="block whitespace-nowrap rounded-lg px-3 py-2 text-xs font-semibold uppercase tracking-wide hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-gym-blue lg:px-2 {{ request()->routeIs('home') ? 'bg-sky-50 text-gym-blue-deep' : 'text-slate-800' }}" href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>Trang chủ</a></li>
                    <li><a class="block whitespace-nowrap rounded-lg px-3 py-2 text-xs font-semibold uppercase tracking-wide hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-gym-blue lg:px-2 {{ request()->routeIs('packages.*') ? 'bg-sky-50 text-gym-blue-deep' : 'text-slate-800' }}" href="{{ route('packages.index') }}" @if (request()->routeIs('packages.*')) aria-current="page" @endif>Gói tập</a></li>
                    <li><a class="block whitespace-nowrap rounded-lg px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-gym-blue lg:px-2" href="{{ route('home') }}#trial">Trải nghiệm</a></li>
                    @auth
                        @if (in_array(auth()->user()->role, ['employee', 'manager'], true))
                            <li>
                                <details class="group relative" name="gym-primary-navigation" data-nav-group="operations">
                                    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 rounded-lg px-3 py-2 text-xs font-semibold uppercase tracking-wide hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-gym-blue lg:justify-center lg:px-2 {{ request()->routeIs('staff.*') || request()->routeIs('gym.invoices.*') ? 'bg-sky-50 text-gym-blue-deep' : 'text-slate-800' }}">
                                        Vận hành <span aria-hidden="true" class="text-[0.65rem]">▾</span>
                                    </summary>
                                    <ul class="mt-1 grid gap-1 border-l border-slate-200 pl-2 lg:absolute lg:left-0 lg:top-full lg:z-50 lg:mt-2 lg:min-w-56 lg:rounded-xl lg:border lg:bg-white lg:p-2 lg:shadow-lg">
                                        <li><a class="block whitespace-nowrap rounded-lg px-3 py-2 text-sm hover:bg-sky-50 hover:text-gym-blue-deep focus-visible:outline-2 focus-visible:outline-gym-blue {{ request()->routeIs('staff.dashboard') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : 'text-slate-700' }}" href="{{ route('staff.dashboard') }}" @if (request()->routeIs('staff.dashboard')) aria-current="page" @endif>Tổng quan vận hành</a></li>
                                        <li><a class="block whitespace-nowrap rounded-lg px-3 py-2 text-sm hover:bg-sky-50 hover:text-gym-blue-deep focus-visible:outline-2 focus-visible:outline-gym-blue {{ request()->routeIs('staff.products.*') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : 'text-slate-700' }}" href="{{ route('staff.products.index') }}" @if (request()->routeIs('staff.products.*')) aria-current="page" @endif>Quản lý sản phẩm</a></li>
                                        @can('viewAny', \App\Models\TrialRequest::class)
                                            <li><a class="block whitespace-nowrap rounded-lg px-3 py-2 text-sm hover:bg-sky-50 hover:text-gym-blue-deep focus-visible:outline-2 focus-visible:outline-gym-blue {{ request()->routeIs('staff.trial-requests.*') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : 'text-slate-700' }}" href="{{ route('staff.trial-requests.index') }}" @if (request()->routeIs('staff.trial-requests.*')) aria-current="page" @endif>Đăng ký tập thử</a></li>
                                        @endcan
                                        @can('viewAny', \App\Models\CheckIn::class)
                                            <li><a class="block whitespace-nowrap rounded-lg px-3 py-2 text-sm hover:bg-sky-50 hover:text-gym-blue-deep focus-visible:outline-2 focus-visible:outline-gym-blue {{ request()->routeIs('staff.checkins.*') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : 'text-slate-700' }}" href="{{ route('staff.checkins.index') }}" @if (request()->routeIs('staff.checkins.*')) aria-current="page" @endif>Check-in</a></li>
                                        @endcan
                                        @can('viewAny', \App\Models\MembershipOrder::class)
                                            <li><a class="block whitespace-nowrap rounded-lg px-3 py-2 text-sm hover:bg-sky-50 hover:text-gym-blue-deep focus-visible:outline-2 focus-visible:outline-gym-blue {{ request()->routeIs('gym.invoices.*') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : 'text-slate-700' }}" href="{{ route('gym.invoices.index') }}" @if (request()->routeIs('gym.invoices.*')) aria-current="page" @endif>Đơn hàng và hóa đơn</a></li>
                                        @endcan
                                    </ul>
                                </details>
                            </li>
                        @endif
                        @if (auth()->user()->role === 'manager')
                            <li>
                                <details class="group relative" name="gym-primary-navigation" data-nav-group="management">
                                    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 rounded-lg px-3 py-2 text-xs font-semibold uppercase tracking-wide hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-gym-blue lg:justify-center lg:px-2 {{ request()->routeIs('manager.*') ? 'bg-sky-50 text-gym-blue-deep' : 'text-slate-800' }}">
                                        Quản lý <span aria-hidden="true" class="text-[0.65rem]">▾</span>
                                    </summary>
                                    <ul class="mt-1 grid gap-1 border-l border-slate-200 pl-2 lg:absolute lg:left-0 lg:top-full lg:z-50 lg:mt-2 lg:min-w-56 lg:rounded-xl lg:border lg:bg-white lg:p-2 lg:shadow-lg">
                                        <li><a class="block whitespace-nowrap rounded-lg px-3 py-2 text-sm hover:bg-sky-50 hover:text-gym-blue-deep focus-visible:outline-2 focus-visible:outline-gym-blue {{ request()->routeIs('manager.dashboard') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : 'text-slate-700' }}" href="{{ route('manager.dashboard') }}" @if (request()->routeIs('manager.dashboard')) aria-current="page" @endif>Tổng quan quản lý</a></li>
                                        <li><a class="block whitespace-nowrap rounded-lg px-3 py-2 text-sm hover:bg-sky-50 hover:text-gym-blue-deep {{ request()->routeIs('manager.reports.*') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : 'text-slate-700' }}" href="{{ route('manager.reports.index') }}" @if (request()->routeIs('manager.reports.*')) aria-current="page" @endif>Báo cáo đơn hàng</a></li>
                                        <li><a class="block whitespace-nowrap rounded-lg px-3 py-2 text-sm hover:bg-sky-50 hover:text-gym-blue-deep {{ request()->route('resource') === 'customers' || request()->routeIs('gym.admin.customers.show') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : 'text-slate-700' }}" href="{{ route('gym.admin.index', 'customers') }}" @if (request()->route('resource') === 'customers' || request()->routeIs('gym.admin.customers.show')) aria-current="page" @endif>Khách hàng</a></li>
                                        <li><a class="block whitespace-nowrap rounded-lg px-3 py-2 text-sm hover:bg-sky-50 hover:text-gym-blue-deep focus-visible:outline-2 focus-visible:outline-gym-blue {{ request()->routeIs('manager.packages.*') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : 'text-slate-700' }}" href="{{ route('manager.packages.index') }}" @if (request()->routeIs('manager.packages.*')) aria-current="page" @endif>Quản lý gói tập</a></li>
                                        <li><a class="block whitespace-nowrap rounded-lg px-3 py-2 text-sm hover:bg-sky-50 hover:text-gym-blue-deep focus-visible:outline-2 focus-visible:outline-gym-blue {{ request()->routeIs('manager.employees.*') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : 'text-slate-700' }}" href="{{ route('manager.employees.index') }}" @if (request()->routeIs('manager.employees.*')) aria-current="page" @endif>Tài khoản nhân viên</a></li>
                                        <li><a class="block whitespace-nowrap rounded-lg px-3 py-2 text-sm hover:bg-sky-50 hover:text-gym-blue-deep {{ request()->routeIs('manager.settings.*') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : 'text-slate-700' }}" href="{{ route('manager.settings.edit') }}">Cài đặt phòng tập</a></li>
                                        <li><a class="block whitespace-nowrap rounded-lg px-3 py-2 text-sm hover:bg-sky-50 hover:text-gym-blue-deep {{ request()->routeIs('manager.banners.*') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : 'text-slate-700' }}" href="{{ route('manager.banners.index') }}">Ảnh banner</a></li>
                                        <li><a class="block whitespace-nowrap rounded-lg px-3 py-2 text-sm hover:bg-sky-50 hover:text-gym-blue-deep {{ request()->routeIs('manager.knowledge.*') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : 'text-slate-700' }}" href="{{ route('manager.knowledge.index') }}">Tài liệu chatbot</a></li>
                                    </ul>
                                </details>
                            </li>
                        @endif
                    @endauth
                </ul>
                <div class="mt-3 border-t border-slate-100 pt-3 lg:mt-0 lg:shrink-0 lg:border-0 lg:pt-0">
                    @auth
                        <details class="group relative" name="gym-primary-navigation" data-account-menu>
                            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-center gap-2 rounded-full bg-slate-100 px-5 py-2 font-semibold text-slate-800 hover:bg-slate-200 focus-visible:outline-2 focus-visible:outline-gym-blue lg:whitespace-nowrap" data-account-trigger>
                                <span class="max-w-40 truncate" title="{{ auth()->user()->name }}">{{ auth()->user()->name }}</span><span aria-hidden="true">▾</span>
                            </summary>
                            <ul class="mt-2 grid gap-1 rounded-xl border border-slate-200 bg-white p-2 shadow-lg lg:absolute lg:right-0 lg:top-full lg:min-w-56">
                                @if (auth()->user()->role === 'customer')
                                    <li><a class="block rounded-lg px-3 py-2 hover:bg-slate-50 {{ request()->routeIs('my.memberships.*') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : '' }}" href="{{ route('my.memberships.index') }}" @if (request()->routeIs('my.memberships.*')) aria-current="page" @endif>Gói hội viên</a></li>
                                    <li><a class="block rounded-lg px-3 py-2 hover:bg-slate-50 {{ request()->routeIs('gym.invoices.*') || request()->routeIs('my.orders.*') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : '' }}" href="{{ route('gym.invoices.index') }}" @if (request()->routeIs('gym.invoices.*') || request()->routeIs('my.orders.*')) aria-current="page" @endif>Đơn hàng và hóa đơn</a></li>
                                    <li><a class="block rounded-lg px-3 py-2 hover:bg-slate-50 {{ request()->routeIs('my.attendance.*') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : '' }}" href="{{ route('my.attendance.index') }}" @if (request()->routeIs('my.attendance.*')) aria-current="page" @endif>Lịch sử tập</a></li>
                                    <li><a class="block rounded-lg px-3 py-2 hover:bg-slate-50 {{ request()->routeIs('trials.*') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : '' }}" href="{{ route('trials.create') }}">Tập thử</a></li>
                                    <li><a class="block rounded-lg px-3 py-2 hover:bg-slate-50 {{ request()->routeIs('my.profile.*') ? 'bg-sky-50 font-semibold text-gym-blue-deep' : '' }}" href="{{ route('my.profile.show') }}" @if (request()->routeIs('my.profile.*')) aria-current="page" @endif>Thông tin cá nhân</a></li>
                                @elseif (auth()->user()->role === 'manager')
                                    <li><a class="block rounded-lg px-3 py-2 hover:bg-slate-50" href="{{ route('manager.dashboard') }}">Tổng quan quản lý</a></li>
                                @else
                                    <li><a class="block rounded-lg px-3 py-2 hover:bg-slate-50" href="{{ route('staff.dashboard') }}">Tổng quan vận hành</a></li>
                                @endif
                                <li><a class="block rounded-lg px-3 py-2 hover:bg-slate-50" href="{{ route('account.password.edit') }}">Đổi mật khẩu</a></li>
                                <li class="my-1 border-t border-slate-100"></li>
                                <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-red-700 hover:bg-red-50" type="submit">Đăng xuất</button></form></li>
                            </ul>
                        </details>
                    @else
                        <a class="gym-btn-outline min-h-10 px-5 py-2 text-sm" href="{{ route('login') }}">Đăng nhập</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <div class="pt-20">
        @if (request()->routeIs('home'))
            <section class="relative isolate h-56 overflow-hidden bg-slate-900 sm:h-72 lg:h-[26rem]" data-carousel aria-label="Hình ảnh The Gym">
            @forelse (($heroBanners ?? collect()) as $banner)
                <div class="absolute inset-0 {{ $loop->first ? '' : 'hidden' }}" data-carousel-slide>
                    <img class="h-full w-full object-cover" src="{{ asset('storage/'.$banner->image_path) }}" alt="{{ $banner->alt_text }}" width="1600" height="700" @if ($loop->first) fetchpriority="high" @endif>
                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950/80 via-slate-950/35 to-transparent px-6 pb-20 pt-24 text-white sm:px-12">
                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-sky-200">{{ $gymSettings?->gym_name ?? 'The Gym' }}</p>
                        <h2 class="mt-2 max-w-3xl text-2xl font-extrabold sm:text-4xl">{{ $banner->title }}</h2>
                    </div>
                </div>
            @empty
                <div class="absolute inset-0" data-carousel-slide><img class="h-full w-full object-cover" src="{{ asset('images/csharp/poster.jpeg') }}" alt="Không gian tập luyện The Gym" width="1600" height="700" fetchpriority="high"></div>
                <div class="absolute inset-0 hidden" data-carousel-slide><img class="h-full w-full object-cover" src="{{ asset('images/csharp/poster1.jpeg') }}" alt="Thiết bị tập luyện tại The Gym" width="1600" height="700"></div>
                <div class="absolute inset-0 hidden" data-carousel-slide><img class="h-full w-full object-cover" src="{{ asset('images/csharp/poster2.jpeg') }}" alt="Bắt đầu hành trình tập luyện" width="1600" height="700"></div>
            @endforelse
            @php($slideCount = isset($heroBanners) && $heroBanners->isNotEmpty() ? $heroBanners->count() : 3)
            <div class="absolute inset-x-0 bottom-4 flex items-center justify-center gap-3">
                @for ($slide = 0; $slide < $slideCount; $slide++)
                    <button class="grid size-11 place-items-center rounded-full focus-visible:outline-2 focus-visible:outline-white" type="button" data-carousel-go="{{ $slide }}" aria-label="Xem ảnh {{ $slide + 1 }}" aria-current="{{ $slide === 0 ? 'true' : 'false' }}"><span class="h-1 w-7 bg-white/50 in-aria-[current=true]:bg-white" aria-hidden="true"></span></button>
                @endfor
            </div>
            </section>
        @endif
    </div>

    @if (session('success'))
        <div class="gym-container mt-4" role="status" aria-live="polite"><p class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900">{{ session('success') }}</p></div>
    @endif
    @if (session('error'))
        <div class="gym-container mt-4" role="alert" aria-live="assertive"><p class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-900">{{ session('error') }}</p></div>
    @endif
    @if ($errors->any())
                    <div class="gym-container mt-4" role="alert" aria-live="assertive"><div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-900"><p class="font-semibold">Kiểm tra lại thông tin:</p><ul class="mt-1 list-inside list-disc">@foreach ($errors->getBag('default')->messages() as $field => $messages) @foreach ($messages as $error)<li><a class="underline underline-offset-2" href="#{{ str_replace('.', '-', $field) }}">{{ $error }}</a></li>@endforeach @endforeach</ul></div></div>
    @endif

    <main id="main" class="min-h-[22rem] pb-20 sm:pb-8" tabindex="-1">
        @yield('content')
    </main>

    <footer class="mt-12 border-t border-slate-200 bg-gym-paper py-10 text-gym-ink">
        <div class="gym-container">
            <nav class="flex flex-wrap justify-center gap-x-8 gap-y-3 text-sm font-medium" aria-label="Điều hướng chân trang">
                <a class="hover:text-gym-blue focus-visible:outline-2 focus-visible:outline-gym-blue" href="{{ route('home') }}">Về The Gym</a>
                <a class="hover:text-gym-blue focus-visible:outline-2 focus-visible:outline-gym-blue" href="{{ route('packages.index') }}">Gói hội viên</a>
                <a class="hover:text-gym-blue focus-visible:outline-2 focus-visible:outline-gym-blue" href="{{ route('home') }}#trial">Trải nghiệm</a>
                <a class="hover:text-gym-blue focus-visible:outline-2 focus-visible:outline-gym-blue" href="{{ route('login') }}">Chăm sóc hội viên</a>
            </nav>
            <p class="mt-6 border-t border-slate-200 pt-5 text-center text-xs text-slate-500">© {{ now(config('app.timezone'))->year }} The Gym · Tập luyện trong không gian không phán xét.</p>
            @if (! empty($gymSettings?->address) || ! empty($gymSettings?->phone) || ! empty($gymSettings?->opening_hours))
                <p class="mt-2 text-center text-xs text-slate-500">{{ $gymSettings?->address }} @if ($gymSettings?->phone) · {{ $gymSettings->phone }} @endif @if ($gymSettings?->opening_hours) · {{ $gymSettings->opening_hours }} @endif</p>
            @endif
        </div>
    </footer>

    @include('components.chatbot')
</body>
</html>
