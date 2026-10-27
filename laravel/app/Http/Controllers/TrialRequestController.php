<?php

namespace App\Http\Controllers;

use App\Models\TrialRequest;
use App\Models\User;
use App\Repositories\TrialRequestRepository;
use App\Services\OriginalGymSql;
use App\Services\TrialRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrialRequestController extends Controller
{
    public function __construct(private TrialRequestRepository $requests) {}

    public function create(Request $request): View
    {
        return view('trials.create', ['trials' => TrialRequest::query()->where('user_id', $request->user()->id)->latest()->with('entitlement')->paginate(10)]);
    }

    public function store(Request $request, OriginalGymSql $sql, TrialRegistrationService $trials): RedirectResponse
    {
        $validated = $request->validate([
            'desired_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $sql->transaction(function () use ($request, $validated, $trials): void {
            $customer = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $trials->assertEligible($customer);
            $this->requests->create([
                'user_id' => $customer->id, 'name' => $customer->name, 'phone' => $customer->phone,
                'email' => $customer->email, 'desired_date' => $validated['desired_date'] ?? now()->toDateString(), 'status' => 'pending',
            ]);
        });

        return redirect()->route('trials.create')->with('success', 'Đã nhận đăng ký trải nghiệm. The Gym sẽ liên hệ với bạn sớm.');
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected,cancelled,received,contacted'],
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

    public function linkLegacyAccount(Request $request, TrialRequest $trialRequest, TrialRegistrationService $trials): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:60'],
            'phone_confirmed' => ['required', 'string', 'min:6', 'max:20'],
            'identity_verified' => ['accepted'],
        ]);
        $trials->linkLegacyRequest($trialRequest, $request->user(), $data['username'], $data['phone_confirmed']);

        return redirect()->route('staff.trial-requests.index')->with('success', 'Đã đối chiếu tài khoản; yêu cầu đang chờ duyệt lại.');
    }

    public function approve(Request $request, TrialRequest $trialRequest, TrialRegistrationService $trials): RedirectResponse
    {
        $data = $request->validate(['identity_verified' => 'accepted', 'start_date' => 'required|date|after_or_equal:today']);
        $trials->approve($trialRequest, $request->user(), $data['start_date']);

        return back()->with('success', 'Đã duyệt quyền tập thử cho tài khoản hội viên.');
    }

    public function cancel(Request $request, TrialRequest $trialRequest, TrialRegistrationService $trials): RedirectResponse
    {
        $trials->cancel($trialRequest, $request->user());

        return redirect()->route('trials.create')->with('success', 'Đã hủy yêu cầu tập thử.');
    }

    public function reject(Request $request, TrialRequest $trialRequest, TrialRegistrationService $trials): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $trials->reject($trialRequest, $request->user(), $data['reason']);

        return back()->with('success', 'Đã lưu lý do từ chối yêu cầu.');
    }

    public function reschedule(Request $request, TrialRequest $trialRequest, TrialRegistrationService $trials): RedirectResponse
    {
        $data = $request->validate(['start_date' => ['required', 'date', 'after_or_equal:today']]);
        $trials->reschedule($trialRequest, $request->user(), $data['start_date']);

        return back()->with('success', 'Đã đổi ngày bắt đầu tập thử.');
    }

    public function revoke(Request $request, TrialRequest $trialRequest, TrialRegistrationService $trials): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $trials->revoke($trialRequest, $request->user(), $data['reason']);

        return back()->with('success', 'Đã thu hồi quyền tập thử và lưu lý do xử lý.');
    }
}
