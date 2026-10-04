<?php

namespace App\Http\Controllers;

use App\Models\TrialRequest;
use App\Models\User;
use App\Repositories\TrialRequestRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrialRequestController extends Controller
{
    public function __construct(private TrialRequestRepository $requests) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $this->requests->create($validated);

        return back()->with('success', 'Đã nhận đăng ký trải nghiệm. The Gym sẽ liên hệ với bạn sớm.');
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:received,contacted'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        return view('staff.trial-requests.index', [
            'trialRequests' => $this->requests->paginateForStaff($filters['status'] ?? null, $filters['search'] ?? null),
            'filters' => $filters,
        ]);
    }

    public function markContacted(Request $request, TrialRequest $trialRequest): RedirectResponse
    {
        $staff = $request->user();
        abort_unless($staff instanceof User, 403);

        $this->requests->markContacted($trialRequest, $staff);

        return redirect()->route('staff.trial-requests.index')->with('success', 'Đã lưu yêu cầu này là đã liên hệ.');
    }
}
