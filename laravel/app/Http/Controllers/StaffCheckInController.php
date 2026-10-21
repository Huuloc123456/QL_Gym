<?php

namespace App\Http\Controllers;

use App\Models\CheckIn;
use App\Repositories\CheckInRepository;
use App\Services\CheckInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffCheckInController extends Controller
{
    public function index(Request $request, CheckInRepository $checkInRepository): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:open,history,all'],
            'q' => ['nullable', 'string', 'max:100'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);

        $filters['status'] ??= 'open';
        if (
            ! empty($filters['from_date'])
            && ! empty($filters['to_date'])
            && $filters['from_date'] > $filters['to_date']
        ) {
            return redirect()->route('staff.checkins.index', $request->query())->withErrors([
                'to_date' => 'Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.',
            ]);
        }

        $visits = $checkInRepository->staffHistory($filters);

        $openVisits = $checkInRepository->openVisits();

        return view('staff.checkins.index', compact('visits', 'openVisits', 'filters'));
    }

    public function store(Request $request, CheckInService $service): RedirectResponse
    {
        $validated = $request->validate([
            'member' => ['required', 'string', 'max:100'],
        ]);

        $service->checkIn($validated['member']);

        return redirect()->route('staff.checkins.index')->with('success', 'Đã ghi nhận hội viên vào tập.');
    }

    public function checkOut(Request $request, CheckIn $visit, CheckInService $service): RedirectResponse
    {
        $service->checkOut($visit, $request->user());

        return redirect()->route('staff.checkins.index')->with('success', 'Đã ghi nhận giờ ra.');
    }

    public function manualClose(Request $request, CheckIn $visit, CheckInService $service): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $service->manualClose($visit, $request->user(), $validated['reason']);

        return redirect()->route('staff.checkins.index')->with('success', 'Đã đóng lượt tập kèm lý do.');
    }
}
