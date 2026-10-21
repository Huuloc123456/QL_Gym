<nav class="fixed inset-x-0 top-0 z-40 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur" aria-label="Điều hướng chính">
    <div class="gym-container flex min-h-20 items-center justify-between gap-3 py-3">
        <a class="shrink-0" href="{{ route('home') }}" aria-label="The Gym - trang chủ"><img class="h-14 w-20 object-contain" src="{{ asset('images/csharp/logo.png') }}" alt="The Gym" width="80" height="54"></a>
        <button class="grid size-11 place-items-center rounded-lg border border-slate-300 lg:hidden" type="button" data-menu-toggle aria-controls="gymNavbar" aria-expanded="false" aria-label="Mở menu"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="size-6 stroke-current" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16" stroke-linecap="round"/></svg></button>
        <div class="absolute inset-x-0 top-full hidden max-h-[calc(100svh-5rem)] overflow-y-auto border-b border-slate-200 bg-white px-4 pb-4 shadow-lg lg:static lg:flex lg:flex-1 lg:items-center lg:justify-between lg:gap-2 lg:overflow-visible lg:border-0 lg:p-0 lg:shadow-none" id="gymNavbar" data-menu>
            <ul class="flex flex-col gap-1 lg:flex-row lg:items-center">
                @foreach ([['home', 'Trang chủ'], ['gym.products.index', 'Sản phẩm'], ['packages.index', 'Gói tập'], ['gym.classes.index', 'Lớp học'], ['gym.pt.index', 'PT']] as [$destination, $label])
                    <li><a class="block whitespace-nowrap rounded-lg px-2 py-2 text-sm font-medium hover:bg-sky-50 hover:text-gym-blue" href="{{ route($destination) }}" @if (request()->routeIs(str_replace('.index', '.*', $destination))) aria-current="page" @endif>{{ $label }}</a></li>
                @endforeach
                <li><a class="block whitespace-nowrap rounded-lg px-2 py-2 text-sm font-medium hover:bg-sky-50 hover:text-gym-blue" href="{{ route('home') }}#trial">Trải nghiệm</a></li>
            </ul>
            <div class="mt-3 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3 lg:mt-0 lg:shrink-0 lg:border-0 lg:pt-0">
                @auth
                    @if (auth()->user()->role === 'customer')
                        <a class="text-sm font-semibold text-gym-blue hover:underline" href="{{ route('gym.cart') }}">Giỏ hàng</a><a class="rounded-full bg-sky-50 px-3 py-2 text-xs font-bold text-gym-blue" href="{{ route('gym.messages') }}" aria-label="Thông báo chưa đọc: {{ $unreadMessages }}">Thông báo @if($unreadMessages)<span class="ml-1">{{ $unreadMessages }}</span>@endif</a>
                        <details class="group relative" name="gym-primary-navigation"><summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold"><span class="max-w-32 truncate">{{ auth()->user()->name }}</span><span aria-hidden="true">▾</span></summary><ul class="mt-2 grid gap-1 rounded-xl border border-slate-200 bg-white p-2 text-sm shadow-lg lg:absolute lg:right-0 lg:top-full lg:min-w-56">
                        @foreach ([['gym.messages', 'Thông báo'], ['gym.security', 'Bảo mật tài khoản'], ['my.memberships.index', 'Gói hội viên'], ['gym.invoices.index', 'Đơn hàng và hóa đơn'], ['gym.calendar', 'Đăng ký và lịch tập'], ['my.attendance.index', 'Lịch sử tập'], ['gym.addresses', 'Địa chỉ nhận hàng'], ['my.profile.show', 'Thông tin cá nhân'], ['gym.login-history', 'Lịch sử đăng nhập']] as [$destination, $label])<li><a class="block rounded-lg px-3 py-2 hover:bg-sky-50" href="{{ route($destination) }}">{{ $label }}</a></li>@endforeach
                        <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-red-700 hover:bg-red-50" type="submit">Đăng xuất</button></form></li></ul></details>
                    @else
                        <a class="gym-btn-outline min-h-10 whitespace-nowrap px-4 py-2 text-sm" href="{{ auth()->user()->role === 'manager' ? route('manager.dashboard') : route('staff.dashboard') }}">Khu vực nội bộ</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="text-sm text-slate-600 underline" type="submit">Đăng xuất</button></form>
                    @endif
                @else<a class="gym-btn-outline min-h-10 px-4 py-2 text-sm" href="{{ route('login') }}">Đăng nhập</a>@endauth
            </div>
        </div>
    </div>
</nav>
