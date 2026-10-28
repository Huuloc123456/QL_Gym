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

    @php($isInternal = auth()->check() && in_array(auth()->user()->role, ['employee', 'manager'], true))
    @if ($isInternal)
        @include('layouts.internal-navigation')
    @else
        @include('layouts.public-navigation')
    @endif
    <div class="{{ $isInternal ? 'lg:pl-[260px]' : '' }}">

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
                <button class="min-h-11 rounded-full bg-slate-950/65 px-4 text-xs font-medium text-white focus-visible:outline-2 focus-visible:outline-white" type="button" data-carousel-pause aria-pressed="false">Tạm dừng</button>
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
            @if (! empty($gymSettings?->address) || ! empty($gymSettings?->phone) || ! empty($gymSettings?->opening_hours))
                <p class="mt-2 text-center text-xs text-slate-500">{{ $gymSettings?->address }} @if ($gymSettings?->phone) · {{ $gymSettings->phone }} @endif @if ($gymSettings?->opening_hours) · {{ $gymSettings->opening_hours }} @endif</p>
            @endif
        </div>
    </footer>

    @include('components.chatbot')
    </div>
</body>
</html>
