@extends('layouts.app')

@section('title', 'Check-in hội viên')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <header class="mb-7">
            <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Khu vực nhân viên</p>
            <h1 class="gym-title mt-2">Check-in hội viên</h1>
            <p class="mt-2 text-slate-600">Tra cứu bằng mã hội viên hoặc số điện thoại. Chỉ gói đã thanh toán và đang hiệu lực mới được vào tập.</p>
        </header>
        <div class="gym-card mb-8">
            <form class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end" method="POST" action="{{ route('staff.checkins.store') }}">
                @csrf
                <div><label class="gym-label" for="member">Mã hội viên hoặc số điện thoại</label><x-form-input id="member" name="member" type="text" inputmode="numeric" autocomplete="off" value="{{ old('member') }}" placeholder="Ví dụ: 0901234567" required /></div>
                <button class="gym-btn-primary w-full sm:w-auto" type="submit">Ghi nhận vào tập</button>
            </form>
        </div>

        <h2 class="mb-3 text-xl font-extrabold uppercase">Lượt chưa ghi giờ ra</h2>
        @include('staff.checkins.table', ['visits' => $openVisits])

        <h2 class="mb-3 mt-8 text-xl font-extrabold uppercase">Lịch sử lượt đã kết thúc</h2>
        @include('staff.checkins.table', ['visits' => $visits])
        <div class="mt-4">{{ $visits->links() }}</div>
    </section>
@endsection
