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
    public function index(CheckInRepository $checkInRepository): View
    {
        $visits = $checkInRepository->staffHistory();

        return view('staff.checkins.index', ['visits' => $visits]);
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
