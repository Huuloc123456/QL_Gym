<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f5f8fa">
    <title>@yield('title', 'The Gym') - The Gym</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a class="sr-only fixed left-4 top-4 z-50 rounded-lg bg-white px-4 py-3 text-gym-ink shadow-lg focus:not-sr-only focus:outline-2 focus:outline-gym-blue" href="#main">Bỏ qua điều hướng</a>

    <nav class="fixed inset-x-0 top-0 z-40 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur" aria-label="Điều hướng chính">
        <div class="gym-container flex min-h-20 items-center justify-between gap-4 py-3">
            <a class="shrink-0" href="{{ route('home') }}" aria-label="The Gym - trang chủ">
                <img src="{{ asset('images/csharp/logo.png') }}" alt="The Gym" width="96" height="64">
            </a>
            <button class="inline-flex size-11 items-center justify-center rounded-lg border border-slate-300 text-gym-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gym-blue md:hidden" type="button" data-menu-toggle aria-controls="gymNavbar" aria-expanded="false" aria-label="Mở menu">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="size-6 stroke-current" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/></svg>
            </button>
            <div class="absolute left-0 right-0 top-full hidden border-b border-slate-200 bg-white px-4 pb-4 shadow-lg md:static md:flex md:items-center md:justify-between md:border-0 md:bg-transparent md:p-0 md:shadow-none" id="gymNavbar" data-menu>
                <ul class="flex flex-col gap-1 md:flex-row md:items-center md:gap-6">
                    <li><a class="block rounded-lg px-3 py-2 text-sm font-semibold uppercase tracking-wide text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-gym-blue" href="{{ route('home') }}">Trang chủ</a></li>
                    <li><a class="block rounded-lg px-3 py-2 text-sm font-semibold uppercase tracking-wide text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-gym-blue" href="{{ route('packages.index') }}">Gói tập</a></li>
                    <li><a class="block rounded-lg px-3 py-2 text-sm font-semibold uppercase tracking-wide text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-gym-blue" href="{{ route('home') }}#trial">Trải nghiệm miễn phí</a></li>
                    @auth
                        @if (in_array(auth()->user()->role, ['employee', 'manager'], true))
                            <li><a class="block rounded-lg px-3 py-2 text-sm font-semibold uppercase tracking-wide text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-gym-blue" href="{{ route('staff.trial-requests.index') }}">Đăng ký tập thử</a></li>
                            <li><a class="block rounded-lg px-3 py-2 text-sm font-semibold uppercase tracking-wide text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-gym-blue" href="{{ route('staff.checkins.index') }}">Check-in</a></li>
                            <li><a class="block rounded-lg px-3 py-2 text-sm font-semibold uppercase tracking-wide text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-gym-blue" href="{{ route('staff.orders.index') }}">Đơn hội viên</a></li>
                        @endif
                        @if (auth()->user()->role === 'manager')
                            <li><a class="block rounded-lg px-3 py-2 text-sm font-semibold uppercase tracking-wide text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-gym-blue" href="{{ route('manager.dashboard') }}">Quản lý</a></li>
                            <li><a class="block rounded-lg px-3 py-2 text-sm font-semibold uppercase tracking-wide text-slate-800 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-gym-blue" href="{{ route('manager.packages.index') }}">Gói tập</a></li>
                        @endif
                    @endauth
                </ul>
                <div class="mt-3 border-t border-slate-100 pt-3 md:mt-0 md:border-0 md:pt-0">
                    @auth
                        @if (auth()->user()->role === 'customer')
                            <details class="group relative">
                                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-center gap-2 rounded-full bg-slate-100 px-5 py-2 font-semibold text-slate-800 hover:bg-slate-200 focus-visible:outline-2 focus-visible:outline-gym-blue">
                                    {{ auth()->user()->name }} <span aria-hidden="true">▾</span>
                                </summary>
                                <ul class="mt-2 grid gap-1 rounded-xl border border-slate-200 bg-white p-2 shadow-lg md:absolute md:right-0 md:top-full md:min-w-52">
                                    <li><a class="block rounded-lg px-3 py-2 hover:bg-slate-50" href="{{ route('my.memberships.index') }}">Gói hội viên</a></li>
                                    <li><a class="block rounded-lg px-3 py-2 hover:bg-slate-50" href="{{ route('my.orders.index') }}">Đơn đăng ký</a></li>
                                    <li><a class="block rounded-lg px-3 py-2 hover:bg-slate-50" href="{{ route('my.attendance.index') }}">Lịch sử tập</a></li>
                                    <li><a class="block rounded-lg px-3 py-2 hover:bg-slate-50" href="{{ route('my.profile.show') }}">Thông tin cá nhân</a></li>
                                    <li class="my-1 border-t border-slate-100"></li>
                                    <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-red-700 hover:bg-red-50" type="submit">Đăng xuất</button></form></li>
                                </ul>
                            </details>
                        @else
                            <div class="flex items-center justify-between gap-3 md:justify-start">
                                <span class="text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</span>
                                <form method="POST" action="{{ route('logout') }}">@csrf<button class="gym-btn-outline min-h-10 px-4 py-2 text-sm" type="submit">Đăng xuất</button></form>
                            </div>
                        @endif
                    @else
                        <a class="gym-btn-outline min-h-10 px-5 py-2 text-sm" href="{{ route('login') }}">Đăng nhập</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <div class="pt-20">
        <section class="relative isolate h-56 overflow-hidden bg-slate-900 sm:h-72 lg:h-[26rem]" data-carousel aria-label="Hình ảnh The Gym">
            <div class="absolute inset-0" data-carousel-slide>
                <img class="h-full w-full object-cover" src="{{ asset('images/csharp/poster.jpeg') }}" alt="Không gian tập luyện The Gym" width="1600" height="700" fetchpriority="high">
            </div>
            <div class="absolute inset-0 hidden" data-carousel-slide>
                <img class="h-full w-full object-cover" src="{{ asset('images/csharp/poster1.jpeg') }}" alt="Thiết bị tập luyện tại The Gym" width="1600" height="700">
            </div>
            <div class="absolute inset-0 hidden" data-carousel-slide>
                <img class="h-full w-full object-cover" src="{{ asset('images/csharp/poster2.jpeg') }}" alt="Bắt đầu hành trình tập luyện" width="1600" height="700">
            </div>
            <div class="absolute inset-x-0 bottom-0 flex items-center justify-between bg-gradient-to-t from-slate-950/70 to-transparent px-4 pb-4 pt-12 sm:px-8 sm:pb-6">
                <button class="gym-btn-outline min-h-10 border-white/70 bg-slate-950/20 px-4 py-2 text-sm text-white hover:bg-white/15" type="button" data-carousel-prev aria-label="Ảnh trước">← Trước</button>
                <span class="sr-only" data-carousel-status aria-live="polite"></span>
                <button class="gym-btn-outline min-h-10 border-white/70 bg-slate-950/20 px-4 py-2 text-sm text-white hover:bg-white/15" type="button" data-carousel-next aria-label="Ảnh tiếp theo">Tiếp →</button>
            </div>
        </section>
    </div>

    @if (session('success'))
        <div class="gym-container mt-4" role="status" aria-live="polite"><p class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900">{{ session('success') }}</p></div>
    @endif
    @if ($errors->any())
        <div class="gym-container mt-4" role="alert" aria-live="assertive"><div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-900"><p class="font-semibold">Kiểm tra lại thông tin:</p><ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif

    <main id="main" class="min-h-[22rem]" tabindex="-1">
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
        </div>
    </footer>
</body>
</html>
