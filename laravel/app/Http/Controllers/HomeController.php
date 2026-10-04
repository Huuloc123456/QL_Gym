<?php

namespace App\Http\Controllers;

use App\Repositories\GymPackageRepository;
use App\Services\MembershipOrderService;
use App\Services\MembershipPeriod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request, MembershipOrderService $orders, MembershipPeriod $period, GymPackageRepository $packageRepository): View
    {
        $packages = $packageRepository->activePackages();

        $customer = $request->user()?->role === 'customer' ? $request->user() : null;
        $suggestedStartDate = $customer
            ? $orders->suggestedStartDate($customer)
            : now(config('app.timezone'))->startOfDay();
        $lastTrainingDays = $packages->mapWithKeys(function ($package) use ($period, $suggestedStartDate): array {
            $expiresOn = $period->expiresOn($suggestedStartDate, $package->duration_months);

            return [$package->id => $period->lastTrainingDay($expiresOn)->format('d/m/Y')];
        });

        return view('home', [
            'packages' => $packages,
            'suggestedStartDate' => $suggestedStartDate,
            'lastTrainingDays' => $lastTrainingDays,
            'selectedPackageId' => $request->integer('package_id'),
        ]);
    }
}
