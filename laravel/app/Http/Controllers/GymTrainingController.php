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

    public function trainers(): View
    {
        $trainers = DB::table('NHANVIEN as n')->join('CHUCVU as c', 'c.MACHUCVU', '=', 'n.MACHUCVU')->leftJoin('gym_pt_rates as r', 'r.employee_id', '=', 'n.MANV')->where('c.TENCHUCVU', 'PT')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('users')->whereColumn('sql_employee_id', 'n.MANV')->where('is_active', false))->select('n.MANV', 'n.TENNV', 'r.price')->orderBy('n.MANV')->get();
        $specialties = DB::table('NHANVIEN_CHUYENMON as p')->join('CHUYENMON as c', 'c.MACM', '=', 'p.MACM')->select('p.MANV', 'c.TENCHUYENMON')->get()->groupBy('MANV');

        return view('gym.trainers', compact('trainers', 'specialties'));
    }

    public function orderPt(Request $request, int $employee): RedirectResponse
    {
        $data = $request->validate(['sessions' => 'required|integer|min:1|max:100', 'payment_method' => 'required|in:cash,bank_transfer,vnpay', 'token' => 'required|uuid']);
        $invoice = $this->shop->checkout($request->user(), $data + ['mode' => 'pickup'], ['employee' => $employee, 'sessions' => $data['sessions']]);

        return redirect()->route('gym.invoices.show', $invoice)->with('success', 'Đã tạo đơn đăng ký PT.');
    }

    public function calendar(Request $request): View
    {
        $user = $request->user();
        $pt = DB::table('DANGKYPT as r')->join('NHANVIEN as n', 'n.MANV', '=', 'r.MANV')->join('KHACHHANG as k', 'k.MAKH', '=', 'r.MAKH')->select('r.*', 'n.TENNV', 'k.TENKH')
            ->selectSub(DB::table('LICHTAPPT')->selectRaw('COUNT(*)')->whereColumn('MADKPT', 'r.MADKPT')->where('TRANGTHAI', 'Đã tập'), 'done');
        $classes = DB::table('DANGKYLOP as r')->join('LOPHOC as l', 'l.MALOP', '=', 'r.MALOP')->join('KHACHHANG as k', 'k.MAKH', '=', 'r.MAKH')->select('r.*', 'l.TENLOP', 'l.NGAYKETTHUC', 'k.TENKH');
        $sessions = DB::table('LICHTAPPT as s')->join('DANGKYPT as r', 'r.MADKPT', '=', 's.MADKPT')->join('NHANVIEN as n', 'n.MANV', '=', 'r.MANV')->join('KHACHHANG as k', 'k.MAKH', '=', 'r.MAKH')->select('s.*', 'r.MANV', 'r.MAKH', 'n.TENNV', 'k.TENKH');
        $classSchedules = DB::table('LICHLOP as s')->join('LOPHOC as l', 'l.MALOP', '=', 's.MALOP')->join('NHANVIEN as n', 'n.MANV', '=', 's.MANV')->select('s.*', 'l.TENLOP', 'n.TENNV');
        if ($user->role === 'customer') {
            $id = $this->identities->customer($user);
            $pt->where('r.MAKH', $id);
            $classes->where('r.MAKH', $id);
            $sessions->where('r.MAKH', $id);
            $classSchedules->whereIn('s.MALOP', DB::table('DANGKYLOP')->select('MALOP')->where('MAKH', $id)->where('TRANGTHAI', '!=', 'Hủy'));
        } elseif ($user->role !== 'manager' && ! $user->canManageOrders()) {
            $id = $this->identities->employee($user);
            $pt->where('r.MANV', $id);
            $sessions->where('r.MANV', $id);
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
        foreach ((clone $sessions)->whereBetween('s.GIOBATDAU', [$start, $end])->get() as $event) {
            $events->push((object) ['date' => substr($event->GIOBATDAU, 0, 10), 'start' => substr($event->GIOBATDAU, 11, 5), 'end' => substr($event->GIOKETTHUC, 11, 5), 'label' => 'PT: '.$event->TENNV, 'customer' => $user->role === 'customer' ? null : $event->TENKH, 'status' => $event->TRANGTHAI, 'kind' => 'pt']);
        }
        foreach ((clone $classSchedules)->whereBetween('s.GIOBATDAU', [$start, $end])->get() as $event) {
            $events->push((object) ['date' => substr($event->GIOBATDAU, 0, 10), 'start' => substr($event->GIOBATDAU, 11, 5), 'end' => substr($event->GIOKETTHUC, 11, 5), 'label' => $event->TENLOP, 'customer' => $event->TENNV, 'status' => $event->TRANGTHAI, 'kind' => 'class']);
        }
        $calendar = ['days' => $days, 'events' => $events->sortBy('start')->groupBy('date'), 'mode' => $mode, 'anchor' => $anchor, 'start' => $start, 'end' => $end, 'previous' => ($mode === 'month' ? $anchor->subMonthNoOverflow() : $anchor->subWeek())->toDateString(), 'next' => ($mode === 'month' ? $anchor->addMonthNoOverflow() : $anchor->addWeek())->toDateString()];

        return view('gym.calendar', ['calendar' => $calendar, 'packageRegistrations' => $user->role === 'manager' ? DB::table('DANGKYGOITAP as r')->join('GOITAP as g', 'g.MAGOITAP', '=', 'r.MAGOITAP')->join('KHACHHANG as k', 'k.MAKH', '=', 'r.MAKH')->select('r.*', 'g.TENGOI', 'k.TENKH')->orderByDesc('r.MADKGT')->paginate(20, ['*'], 'package_page') : null, 'contracts' => $pt->orderByDesc('r.MADKPT')->paginate(15, ['*'], 'pt_page'), 'registrations' => $classes->orderByDesc('r.MADKLOP')->paginate(20, ['*'], 'class_page'), 'ptSessions' => $sessions->orderByDesc('s.GIOBATDAU')->paginate(25, ['*'], 'session_page'), 'classSessions' => $classSchedules->orderByDesc('s.GIOBATDAU')->paginate(25, ['*'], 'lesson_page'), 'courses' => $user->role === 'manager' ? DB::table('LOPHOC')->orderByDesc('MALOP')->get() : [], 'employees' => $user->role === 'manager' ? DB::table('NHANVIEN')->select('MANV', 'TENNV')->orderBy('TENNV')->get() : []]);
    }

    public function book(Request $request, int $contract): RedirectResponse
    {
        $this->training->bookPt($request->user(), $contract, $this->timeData($request));

        return back()->with('success', 'Đã xếp lịch PT.');
    }

    public function classSession(Request $request, int $class): RedirectResponse
    {
        $data = $this->timeData($request) + $request->validate(['employee' => 'required|integer|exists:NHANVIEN,MANV']);
        $this->training->classSession($request->user(), $class, $data);

        return back()->with('success', 'Đã xếp buổi học.');
    }

    public function finish(Request $request, string $kind, int $session): RedirectResponse
    {
        $this->training->finish($request->user(), $kind, $session);

        return back()->with('success', 'Đã xác nhận hoàn thành buổi.');
    }

    public function remove(Request $request, string $kind, int $session): RedirectResponse
    {
        $this->training->removeSession($request->user(), $kind, $session);

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
