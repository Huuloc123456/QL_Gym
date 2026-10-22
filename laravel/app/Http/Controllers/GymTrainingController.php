<?php

namespace App\Http\Controllers;

use App\Services\GymCommerceService;
use App\Services\GymIdentityService;
use App\Services\GymTrainingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GymTrainingController extends Controller
{
    public function __construct(private GymTrainingService $training, private GymCommerceService $shop, private GymIdentityService $identities) {}

    public function classes(Request $request): View
    {
        $data = $request->validate(['specialty' => 'nullable|integer|exists:CHUYENMON,MACM', 'q' => 'nullable|string|max:150']);
        $query = DB::table('LOPHOC as l')->join('CHUYENMON as c', 'c.MACM', '=', 'l.MACM')->select('l.*', 'c.TENCHUYENMON', 'c.MOTA')->when($data['specialty'] ?? null, fn ($q, $id) => $q->where('l.MACM', $id))->when($data['q'] ?? null, fn ($q, $search) => $q->where('TENLOP', 'like', '%'.$search.'%'));

        return view('gym.classes', ['classes' => $query->orderByDesc('NGAYBATDAU')->orderBy('MALOP')->paginate(12)->withQueryString(), 'specialties' => DB::table('CHUYENMON')->orderBy('MACM')->get()]);
    }

    public function showClass(int $class): View
    {
        $course = $this->shop->item('class', $class);
        $enrolled = DB::table('DANGKYLOP')->where('MALOP', $class)->where('TRANGTHAI', 'Còn hiệu lực')->count();
        $reserved = DB::table('gym_invoice_lines as l')->join('HOADON as h', 'h.MAHD', '=', 'l.invoice_id')->where('l.kind', 'class')->where('l.item_id', $class)->where('h.TRANGTHAI', 'Chưa thanh toán')->count();

        return view('gym.class', ['course' => $course, 'enrolled' => $enrolled, 'reserved' => $reserved, 'schedules' => DB::table('LICHLOP as l')->join('NHANVIEN as n', 'n.MANV', '=', 'l.MANV')->where('MALOP', $class)->select('l.*', 'n.TENNV')->orderBy('GIOBATDAU')->get()]);
    }

    public function calendar(Request $request): View
    {
        $user = $request->user();
        $classes = DB::table('DANGKYLOP as r')->join('LOPHOC as l', 'l.MALOP', '=', 'r.MALOP')->join('KHACHHANG as k', 'k.MAKH', '=', 'r.MAKH')->select('r.*', 'l.TENLOP', 'l.NGAYKETTHUC', 'k.TENKH');
        $classSchedules = DB::table('LICHLOP as s')->join('LOPHOC as l', 'l.MALOP', '=', 's.MALOP')->join('NHANVIEN as n', 'n.MANV', '=', 's.MANV')->select('s.*', 'l.TENLOP', 'n.TENNV');
        if ($user->role === 'customer') {
            $id = $this->identities->customer($user);
            $classes->where('r.MAKH', $id);
            $classSchedules->whereIn('s.MALOP', DB::table('DANGKYLOP')->select('MALOP')->where('MAKH', $id)->where('TRANGTHAI', '!=', 'Hủy'));
        } elseif ($user->role === 'employee') {
            $id = $this->identities->employee($user);
            $classSchedules->where('s.MANV', $id);
            $classes->whereIn('r.MALOP', DB::table('LICHLOP')->select('MALOP')->where('MANV', $id));
        }

        $data = $request->validate(['date' => 'nullable|date_format:Y-m-d|after:2000-01-01|before:2100-01-01', 'view' => 'nullable|in:week,month']);
        $anchor = CarbonImmutable::parse($data['date'] ?? now()->toDateString());
        $mode = $data['view'] ?? 'week';
        $start = ($mode === 'month' ? $anchor->startOfMonth() : $anchor)->startOfWeek();
        $end = ($mode === 'month' ? $anchor->endOfMonth() : $anchor)->endOfWeek();
        $days = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $days[] = $day;
        }
        $events = collect();
        foreach ((clone $classSchedules)->whereBetween('s.GIOBATDAU', [$start, $end])->get() as $event) {
            $events->push((object) ['date' => substr($event->GIOBATDAU, 0, 10), 'start' => substr($event->GIOBATDAU, 11, 5), 'end' => substr($event->GIOKETTHUC, 11, 5), 'label' => $event->TENLOP, 'customer' => $event->TENNV, 'status' => $event->TRANGTHAI, 'kind' => 'class']);
        }
        $calendar = ['days' => $days, 'events' => $events->sortBy('start')->groupBy('date'), 'mode' => $mode, 'anchor' => $anchor, 'start' => $start, 'end' => $end, 'previous' => ($mode === 'month' ? $anchor->subMonthNoOverflow() : $anchor->subWeek())->toDateString(), 'next' => ($mode === 'month' ? $anchor->addMonthNoOverflow() : $anchor->addWeek())->toDateString()];

        return view('gym.calendar', [
            'calendar' => $calendar,
            'packageRegistrations' => $user->role === 'manager' ? DB::table('DANGKYGOITAP as r')->join('GOITAP as g', 'g.MAGOITAP', '=', 'r.MAGOITAP')->join('KHACHHANG as k', 'k.MAKH', '=', 'r.MAKH')->select('r.*', 'g.TENGOI', 'k.TENKH')->orderByDesc('r.MADKGT')->paginate(20, ['*'], 'package_page') : null,
            'registrations' => $classes->orderByDesc('r.MADKLOP')->paginate(20, ['*'], 'class_page'),
            'classSessions' => $classSchedules->orderByDesc('s.GIOBATDAU')->paginate(25, ['*'], 'lesson_page'),
            'courses' => $user->role === 'manager' ? DB::table('LOPHOC')->orderByDesc('MALOP')->get() : [],
            'employees' => $user->role === 'manager' ? DB::table('NHANVIEN')->select('MANV', 'TENNV')->orderBy('TENNV')->get() : [],
        ]);
    }

    public function classSession(Request $request, int $class): RedirectResponse
    {
        $data = $this->timeData($request) + $request->validate(['employee' => 'required|integer|exists:NHANVIEN,MANV']);
        $this->training->classSession($request->user(), $class, $data);

        return back()->with('success', 'Đã xếp buổi học.');
    }

    public function finish(Request $request, int $session): RedirectResponse
    {
        $this->training->finish($request->user(), $session);

        return back()->with('success', 'Đã xác nhận hoàn thành buổi.');
    }

    public function remove(Request $request, int $session): RedirectResponse
    {
        $this->training->removeSession($request->user(), $session);

        return back()->with('success', 'Đã hủy lịch chưa bắt đầu.');
    }

    public function end(Request $request, string $kind, int $registration): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:255']);
        $this->training->endRegistration($request->user(), $kind, $registration, $data['reason']);

        return back()->with('success', 'Đã chấm dứt đăng ký. Hóa đơn đã thu được giữ nguyên.');
    }

    private function timeData(Request $request): array
    {
        return $request->validate(['date' => 'required|date|after_or_equal:today', 'start' => 'required|date_format:H:i', 'end' => 'required|date_format:H:i|after:start']);
    }
}
