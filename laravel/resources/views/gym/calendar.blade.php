@extends('layouts.app')
@section('title', 'Đăng ký và lịch lớp học')
@section('content')
<section class="gym-container py-10">
    <h1 class="gym-title">Đăng ký và lịch lớp học</h1>
    @include('gym.calendar-grid')

    @if ($packageRegistrations)
        <h2 class="mt-8 text-xl font-bold">Đăng ký gói tập</h2>
        <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white" tabindex="0" aria-label="Đăng ký gói tập">
            <table class="gym-table">
                <thead><tr><th>Khách</th><th>Gói</th><th>Thời hạn</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                <tbody>
                    @foreach ($packageRegistrations as $registration)
                        <tr>
                            <td>{{ $registration->TENKH }}</td>
                            <td>{{ $registration->TENGOI }}</td>
                            <td>{{ $registration->NGAYBATDAU }} → {{ $registration->NGAYKETTHUC }}</td>
                            <td>{{ $registration->TRANGTHAI }}</td>
                            <td>@include('gym.end-registration', ['kind' => 'package', 'id' => $registration->MADKGT])</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $packageRegistrations->links() }}</div>
    @endif

    <h2 class="mt-8 text-xl font-bold">Đăng ký lớp học</h2>
    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white" tabindex="0" aria-label="Đăng ký lớp học">
        <table class="gym-table">
            <thead><tr><th>Mã đăng ký</th><th>Học viên</th><th>Lớp</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
            <tbody>
                @forelse ($registrations as $registration)
                    <tr>
                        <td>#{{ $registration->MADKLOP }}</td>
                        <td>{{ $registration->TENKH }}</td>
                        <td>{{ $registration->TENLOP }}</td>
                        <td>{{ $registration->TRANGTHAI }}</td>
                        <td>@include('gym.end-registration', ['kind' => 'class', 'id' => $registration->MADKLOP])</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Chưa có đăng ký lớp.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $registrations->links() }}</div>

    <h2 class="mt-8 text-xl font-bold">Lịch lớp học</h2>
    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white" tabindex="0" aria-label="Lịch lớp học">
        <table class="gym-table">
            <thead><tr><th>Lớp</th><th>Nhân viên phụ trách</th><th>Thời gian</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
            <tbody>
                @forelse ($classSessions as $session)
                    <tr>
                        <td>{{ $session->TENLOP }}</td>
                        <td>{{ $session->TENNV }}</td>
                        <td>{{ $session->GIOBATDAU }} → {{ substr($session->GIOKETTHUC, 11, 5) }}</td>
                        <td>{{ $session->TRANGTHAI }}</td>
                        <td>
                            @if (auth()->user()->role === 'manager' || (auth()->user()->role === 'employee' && auth()->user()->sql_employee_id == $session->MANV))
                                <form method="POST" action="{{ route('gym.sessions.finish', $session->MALICHLOP) }}">
                                    @csrf
                                    <button class="text-sm text-gym-blue underline" type="submit">Xác nhận đã diễn ra</button>
                                </form>
                            @endif
                            @if (auth()->user()->role === 'manager' && $session->TRANGTHAI === 'Chưa diễn ra')
                                <form class="mt-2" method="POST" action="{{ route('gym.sessions.remove', $session->MALICHLOP) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-sm text-red-700 underline" type="submit">Hủy buổi chưa bắt đầu</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Chưa có lịch lớp.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $classSessions->links() }}</div>

    @if (auth()->user()->role === 'manager')
        <section class="mt-8">
            <h2 class="text-xl font-bold">Phân công và xếp buổi học</h2>
            <div class="mt-5 grid items-start gap-4 lg:grid-cols-2">
                @foreach ($courses as $course)
                    <form class="gym-card grid gap-3 sm:grid-cols-3" method="POST" action="{{ route('gym.class.session', $course->MALOP) }}">
                        @csrf
                        <h3 class="font-bold sm:col-span-3">{{ $course->TENLOP }}</h3>
                        <div class="sm:col-span-3">
                            <label class="gym-label" for="teacher-{{ $course->MALOP }}">Nhân viên phụ trách (đúng chuyên môn)</label>
                            <select class="gym-input" id="teacher-{{ $course->MALOP }}" name="employee" required>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->MANV }}">{{ $employee->TENNV }}</option>
                                @endforeach
                            </select>
                        </div>
                        @include('gym.time-fields', ['prefix' => 'class-'.$course->MALOP])
                        <button class="gym-btn-primary sm:col-span-3" type="submit">Thêm buổi học</button>
                    </form>
                @endforeach
            </div>
        </section>
    @endif
</section>
@endsection
