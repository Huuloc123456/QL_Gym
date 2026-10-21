<?php

namespace App\Http\Controllers;

use App\Models\GymPackage;
use App\Repositories\CheckInRepository;
use App\Repositories\GymPackageRepository;
use App\Repositories\MembershipRepository;
use App\Services\GymIdentityService;
use App\Services\MembershipOrderService;
use App\Services\MembershipPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function packages(Request $request, MembershipOrderService $orders, MembershipPeriod $period, GymPackageRepository $packageRepository): View
    {
        $packages = $packageRepository->activePackages();

        $customer = $request->user()?->role === 'customer' ? $request->user() : null;
        $suggestedStartDate = $customer
            ? $orders->suggestedStartDate($customer)
            : now(config('app.timezone'))->startOfDay();
        $lastTrainingDays = $packages->mapWithKeys(function (GymPackage $package) use ($period, $suggestedStartDate): array {
            $expiresOn = $period->expiresOn($suggestedStartDate, $package->duration_months);

            return [$package->id => $period->lastTrainingDay($expiresOn)->format('d/m/Y')];
        });

        return view('packages.index', [
            'packages' => $packages,
            'suggestedStartDate' => $suggestedStartDate,
            'lastTrainingDays' => $lastTrainingDays,
            'selectedPackageId' => $request->integer('package_id'),
        ]);
    }

    public function showPackage(GymPackage $package, Request $request, MembershipOrderService $orders, MembershipPeriod $period): View
    {
        abort_unless($package->is_active, 404);

        $customer = $request->user()?->role === 'customer' ? $request->user() : null;
        $suggestedStartDate = $customer
            ? $orders->suggestedStartDate($customer)
            : now(config('app.timezone'))->startOfDay();
        $expiresOn = $period->expiresOn($suggestedStartDate, $package->duration_months);

        return view('packages.show', [
            'package' => $package,
            'suggestedStartDate' => $suggestedStartDate,
            'lastTrainingDay' => $period->lastTrainingDay($expiresOn),
        ]);
    }

    public function memberships(Request $request, MembershipRepository $membershipsRepository): View
    {
        $today = now(config('app.timezone'))->toDateString();
        $activeMemberships = $membershipsRepository->paginateForCustomerSection($request->user(), 'active', $today);
        $upcomingMemberships = $membershipsRepository->paginateForCustomerSection($request->user(), 'upcoming', $today);
        $expiredMemberships = $membershipsRepository->paginateForCustomerSection($request->user(), 'expired', $today);
        $customerId = app(GymIdentityService::class)->customer($request->user());
        $sqlMemberships = DB::table('DANGKYGOITAP as d')
            ->join('GOITAP as g', 'g.MAGOITAP', '=', 'd.MAGOITAP')
            ->leftJoin('trial_requests as t', 't.sql_membership_id', '=', 'd.MADKGT')
            ->where('d.MAKH', $customerId)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('memberships as m')->whereColumn('m.sql_membership_id', 'd.MADKGT'))
            ->select('d.*', 'g.TENGOI', 't.id as trial_request_id')
            ->orderByDesc('d.NGAYBATDAU')
            ->paginate(10, ['*'], 'legacy_page')
            ->withQueryString();

        return view('memberships.index', compact('activeMemberships', 'upcomingMemberships', 'expiredMemberships', 'sqlMemberships', 'today'));
    }

    public function attendance(Request $request, CheckInRepository $checkInRepository): View
    {
        $visits = $checkInRepository->forCustomer($request->user())->paginate(20);

        return view('memberships.attendance', ['visits' => $visits]);
    }
}
