<?php

namespace App\Http\Controllers;

use App\Models\GymSetting;
use App\Models\HeroBanner;
use App\Repositories\GymPackageRepository;
use App\Services\GymCommerceService;
use App\Services\MembershipOrderService;
use App\Services\MembershipPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        $gymSettings = GymSetting::query()->find(1);

        return view('home', [
            'packages' => $packages,
            'suggestedStartDate' => $suggestedStartDate,
            'lastTrainingDays' => $lastTrainingDays,
            'selectedPackageId' => $request->integer('package_id'),
            'featuredProducts' => Schema::hasTable('SANPHAM')
                ? app(GymCommerceService::class)->availableProductsQuery()
                    ->selectSub(DB::table('HINHANH')->select('URL')->whereColumn('MASP', 'p.MASP')->orderByDesc('ISMAIN')->orderBy('MAHINH')->limit(1), 'image')
                    ->whereRaw('p.SOLUONGTON - p.reserved_quantity > 0')->orderByRaw('GIAKHUYENMAI IS NULL')->orderBy('MASP')->limit(10)->get()
                : collect(),
            'heroBanners' => HeroBanner::query()->where('is_active', true)->orderBy('position')->orderBy('id')->get(),
            'gymSettings' => $gymSettings,
            'faqs' => $gymSettings?->faq_items ?? [],
        ]);
    }
}
