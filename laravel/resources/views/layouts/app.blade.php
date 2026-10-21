<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f5f8fa">
    <title>@yield('title', 'The Gym') - The Gym</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $isInternal = request()->routeIs('staff.*', 'manager.*', 'gym.admin.*', 'gym.database.*') || (in_array(auth()->user()?->role, ['employee', 'manager'], true) && request()->routeIs('gym.invoices.*', 'gym.calendar', 'gym.sessions.*', 'gym.reports*', 'gym.settings*', 'gym.messages*', 'gym.security'));
    $isAuthPage = request()->routeIs('login', 'login.otp', 'register', 'password.*');
@endphp
<body @class(['gym-internal' => $isInternal])>
    <a class="sr-only fixed left-4 top-4 z-50 rounded-lg bg-white px-4 py-3 text-gym-ink shadow-lg focus:not-sr-only focus:outline-2 focus:outline-gym-blue" href="#main">Bỏ qua điều hướng</a>

    @if ($isInternal)
        @include('layouts.internal-navigation')
    @else
        @include('layouts.public-navigation')
    @endif

    <div class="gym-page {{ $isInternal ? '' : 'pt-20' }}">
        @if ($isInternal)
            <header class="flex min-h-20 items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 sm:px-8 lg:sticky lg:top-0 lg:z-30">
                <p class="text-sm text-slate-500">The Gym <span class="mx-2" aria-hidden="true">/</span> <span class="font-medium text-slate-800">@yield('title')</span></p>
                <div class="flex shrink-0 items-center gap-3 text-sm"><span class="grid size-9 place-items-center rounded-full bg-indigo-600 font-bold text-white" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><span class="hidden sm:block">{{ auth()->user()->name }}</span></div>
            </header>
        @endif
    <div>
        @if (request()->routeIs('home'))
            <section class="relative isolate h-56 overflow-hidden bg-slate-900 sm:h-80 lg:h-[470px]" data-carousel aria-label="Hình ảnh The Gym">
            <div class="absolute inset-0" data-carousel-slide>
                <img class="h-full w-full object-cover" src="{{ asset('images/csharp/poster.jpeg') }}" alt="Không gian tập luyện The Gym" width="1600" height="700" fetchpriority="high">
            </div>
            <div class="absolute inset-0 hidden" data-carousel-slide>
                <img class="h-full w-full object-cover" src="{{ asset('images/csharp/poster1.jpeg') }}" alt="Thiết bị tập luyện tại The Gym" width="1600" height="700">
            </div>
            <div class="absolute inset-0 hidden" data-carousel-slide>
                <img class="h-full w-full object-cover" src="{{ asset('images/csharp/poster2.jpeg') }}" alt="Bắt đầu hành trình tập luyện" width="1600" height="700">
            </div>
            <div class="absolute inset-x-0 bottom-4 flex items-center justify-center gap-3">
                @for ($slide = 0; $slide < 3; $slide++)
                    <button class="grid size-11 place-items-center rounded-full focus-visible:outline-2 focus-visible:outline-white" type="button" data-carousel-go="{{ $slide }}" aria-label="Xem ảnh {{ $slide + 1 }}" aria-current="{{ $slide === 0 ? 'true' : 'false' }}"><span class="h-1 w-7 bg-white/50 in-aria-[current=true]:bg-white" aria-hidden="true"></span></button>
                @endfor
                <button class="min-h-11 rounded-full bg-slate-950/65 px-4 text-xs font-medium text-white focus-visible:outline-2 focus-visible:outline-white" type="button" data-carousel-pause aria-pressed="false">Tạm dừng</button>
            </div>
            </section>
        @endif
    </div>

    @if ($isAuthPage)
        <div class="gym-auth-shell">
            @include('auth.introduction')
            <div class="gym-auth-content">
    @endif

    @if (session('success'))
        <div class="gym-container mt-4" role="status" aria-live="polite"><p class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900">{{ session('success') }}</p></div>
    @endif
    @if ($errors->any())
                    <div class="gym-container mt-4" role="alert" aria-live="assertive"><div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-900"><p class="font-semibold">Kiểm tra lại thông tin:</p><ul class="mt-1 list-inside list-disc">@foreach ($errors->getBag('default')->messages() as $field => $messages) @foreach ($messages as $error)<li><a class="underline underline-offset-2" href="#{{ str_replace('.', '-', $field) }}">{{ $error }}</a></li>@endforeach @endforeach</ul></div></div>
    @endif

    <main id="main" class="min-h-[22rem]" tabindex="-1">
        @yield('content')
    </main>

    @if ($isAuthPage)
            </div>
        </div>
    @endif

    @if ($isInternal)
        <footer class="mt-8 border-t border-slate-200 bg-white px-8 py-5 text-sm text-slate-500">© {{ now(config('app.timezone'))->year }} The Gym · Quản lý phòng tập</footer>
    @else
    <footer class="mt-12 border-t border-slate-200 bg-white py-10 text-gym-ink">
        <div class="gym-container">
            <nav class="flex flex-wrap justify-center gap-x-8 gap-y-3 text-sm font-medium" aria-label="Điều hướng chân trang">
                <a class="hover:text-gym-blue focus-visible:outline-2 focus-visible:outline-gym-blue" href="{{ route('home') }}">Về The Gym</a>
                <a class="hover:text-gym-blue focus-visible:outline-2 focus-visible:outline-gym-blue" href="{{ route('packages.index') }}">Gói hội viên</a>
                <a class="hover:text-gym-blue focus-visible:outline-2 focus-visible:outline-gym-blue" href="{{ route('home') }}#trial">Trải nghiệm</a>
                <a class="hover:text-gym-blue" href="{{ route('gym.contact') }}">Liên hệ</a><a class="hover:text-gym-blue" href="{{ route('gym.help') }}">Hướng dẫn</a><a class="hover:text-gym-blue" href="{{ route('gym.policy') }}">Chính sách</a>
            </nav>
            <p class="mt-6 border-t border-slate-200 pt-5 text-center text-xs text-slate-500">© {{ now(config('app.timezone'))->year }} The Gym · Tập luyện trong không gian không phán xét.</p>
        </div>
    </footer>
    @endif

    </div>

    @include('components.chatbot')
</body>
</html>
