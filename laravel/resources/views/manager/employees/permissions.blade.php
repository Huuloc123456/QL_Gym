@extends('layouts.app')

@section('title', 'Phân quyền nhân viên')

@section('content')
    <section class="gym-container py-10 sm:py-14">
        <div class="mx-auto max-w-3xl">
            <header class="mb-6">
                <p class="text-sm font-bold uppercase tracking-[0.18em] text-gym-blue">Khu vực quản lý</p>
                <h1 class="gym-title mt-2">Phân quyền nghiệp vụ</h1>
                <p class="mt-2 text-slate-600">Chọn những phần {{ $employee->name }} được phép xử lý. Quản lý luôn có đầy đủ quyền.</p>
            </header>

            <div class="gym-card mb-5 border-sky-200 bg-sky-50 text-sm leading-6 text-sky-950">
                Quyền được kiểm tra mỗi lần nhân viên mở trang hoặc gửi thao tác. Tắt một quyền sẽ ẩn mục đó khỏi điều hướng và chặn cả đường dẫn trực tiếp.
            </div>

            <form class="gym-card space-y-5 p-6 sm:p-8" method="POST" action="{{ route('manager.employees.permissions.update', $employee) }}">
                @csrf
                @method('PUT')

                <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-gym-ink">Tiếp nhận tập thử</h2>
                        <p class="mt-1 max-w-xl text-sm leading-6 text-slate-600">Xem danh sách đăng ký trải nghiệm và đánh dấu đã liên hệ khách.</p>
                    </div>
                    <label class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-4 py-2 font-semibold text-gym-ink has-[:checked]:border-gym-blue has-[:checked]:bg-sky-50">
                        <input type="hidden" name="can_manage_trial_requests" value="0">
                        <input class="size-5 accent-gym-blue" type="checkbox" name="can_manage_trial_requests" value="1" @checked((bool) old('can_manage_trial_requests', $employee->can_manage_trial_requests))>
                        <span>Được phép</span>
                    </label>
                </div>

                <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-gym-ink">Đơn hàng và thanh toán</h2>
                        <p class="mt-1 max-w-xl text-sm leading-6 text-slate-600">Xem đơn đăng ký, ghi nhận tiền đã nhận, xác nhận gói tập hoặc hủy đơn chưa thu tiền.</p>
                    </div>
                    <label class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-4 py-2 font-semibold text-gym-ink has-[:checked]:border-gym-blue has-[:checked]:bg-sky-50">
                        <input type="hidden" name="can_manage_orders" value="0">
                        <input class="size-5 accent-gym-blue" type="checkbox" name="can_manage_orders" value="1" @checked((bool) old('can_manage_orders', $employee->can_manage_orders))>
                        <span>Được phép</span>
                    </label>
                </div>

                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-gym-ink">Check-in hội viên</h2>
                        <p class="mt-1 max-w-xl text-sm leading-6 text-slate-600">Tra cứu hội viên, ghi nhận vào tập, ghi giờ ra hoặc đóng lượt tập thủ công.</p>
                    </div>
                    <label class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-4 py-2 font-semibold text-gym-ink has-[:checked]:border-gym-blue has-[:checked]:bg-sky-50">
                        <input type="hidden" name="can_manage_check_ins" value="0">
                        <input class="size-5 accent-gym-blue" type="checkbox" name="can_manage_check_ins" value="1" @checked((bool) old('can_manage_check_ins', $employee->can_manage_check_ins))>
                        <span>Được phép</span>
                    </label>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-between">
                    <a class="gym-btn-outline" href="{{ route('manager.employees.index') }}">Quay lại danh sách</a>
                    <button class="gym-btn-primary" type="submit">Lưu quyền</button>
                </div>
            </form>
        </div>
    </section>
@endsection
