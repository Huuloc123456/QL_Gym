<?php

namespace App\Http\Controllers;

use App\Models\TrialRequest;
use App\Models\User;
use App\Repositories\TrialRequestRepository;
use App\Services\GymIdentityService;
use App\Services\OriginalGymSql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
            'trialPackages' => DB::table('GOITAP')->whereBetween('THOIHAN', [1, 7])->orderBy('THOIHAN')->get(),
        ]);
    }

    public function markContacted(Request $request, TrialRequest $trialRequest): RedirectResponse
    {
        $staff = $request->user();
        abort_unless($staff instanceof User, 403);

        $this->requests->markContacted($trialRequest, $staff);

        return redirect()->route('staff.trial-requests.index')->with('success', 'Đã lưu yêu cầu này là đã liên hệ.');
    }

    public function approve(Request $request, TrialRequest $trialRequest, OriginalGymSql $sql, GymIdentityService $identities): RedirectResponse
    {
        $data = $request->validate(['package' => 'required|integer|exists:GOITAP,MAGOITAP', 'identity_verified' => 'accepted']);
        $sql->transaction(function () use ($request, $trialRequest, $sql, $data, $identities): void {
            $trial = TrialRequest::query()->lockForUpdate()->findOrFail($trialRequest->id);
            if ($trial->sql_membership_id) {
                return;
            }
            $duration = (int) DB::table('GOITAP')->where('MAGOITAP', $data['package'])->value('THOIHAN');
            if ($duration < 1 || $duration > 7 || TrialRequest::query()->where('phone', $trial->phone)->whereNotNull('sql_membership_id')->exists()) {
                throw ValidationException::withMessages(['package' => 'Chỉ duyệt thử 1–7 ngày và một lần cho mỗi số điện thoại.']);
            }
            $existing = DB::table('KHACHHANG')->where('SDT', $trial->phone)->first();
            if ($existing && DB::table('DANGKYGOITAP')->where('MAKH', $existing->MAKH)->where('TRANGTHAI', 'Còn hiệu lực')->where('NGAYKETTHUC', '>=', now())->exists()) {
                throw ValidationException::withMessages(['trial' => 'Khách đã có quyền tập còn hiệu lực.']);
            }
            $result = $sql->call('SP_DANGKYTAPTHU', [mb_substr($trial->name, 0, 150), $trial->phone, $data['package']]);
            if (! $existing) {
                DB::table('KHACHHANG')->where('SDT', $trial->phone)->update(['MATKHAU' => Hash::make(Str::random(40))]);
            }
            $person = DB::table('KHACHHANG')->where('SDT', $trial->phone)->first();
            if (! $identities->importAccount($person, 'customer')) {
                throw ValidationException::withMessages(['trial' => 'Tên tài khoản trùng, cần đối chiếu khách trước khi duyệt.']);
            }
            $trial->forceFill(['sql_membership_id' => $result[0][0]->MADKGT, 'status' => 'contacted', 'handled_by' => $request->user()->id, 'handled_at' => now()])->save();
            $sql->audit('Duyệt tập thử', 'DANGKYGOITAP:'.$result[0][0]->MADKGT);
        });

        return back()->with('success', 'Đã duyệt tập thử theo thời lượng ngày của gói được chọn.');
    }
}
