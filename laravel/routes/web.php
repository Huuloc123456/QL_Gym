<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ManagerDashboardController;
use App\Http\Controllers\ManagerGymPackageController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MembershipOrderController;
use App\Http\Controllers\PasswordRecoveryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaffCheckInController;
use App\Http\Controllers\StaffOrderController;
use App\Http\Controllers\TrialRequestController;
use App\Models\CheckIn;
use App\Models\GymPackage;
use App\Models\MembershipOrder;
use App\Models\TrialRequest;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/goi-tap', [MemberController::class, 'packages'])->name('packages.index');
Route::get('/goi-tap/{package}', [MemberController::class, 'showPackage'])->name('packages.show');
Route::post('/dang-ky-trai-nghiem', [TrialRequestController::class, 'store'])->name('trials.store');

Route::middleware('guest')->group(function (): void {
    Route::get('/dang-nhap', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/dang-nhap', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.store');
    Route::get('/dang-ky', [AuthController::class, 'showRegistration'])->name('register');
    Route::post('/dang-ky', [AuthController::class, 'register'])->name('register.store');
    Route::get('/quen-mat-khau', [PasswordRecoveryController::class, 'create'])->name('password.request');
    Route::post('/quen-mat-khau', [PasswordRecoveryController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('password.email');
    Route::get('/dat-lai-mat-khau/{token}', [PasswordRecoveryController::class, 'edit'])->name('password.reset');
    Route::post('/dat-lai-mat-khau', [PasswordRecoveryController::class, 'update'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

Route::post('/dang-xuat', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:customer'])->prefix('my')->name('my.')->group(function (): void {
    Route::get('/goi-tap', [MemberController::class, 'memberships'])->name('memberships.index');
    Route::get('/lich-su-tap', [MemberController::class, 'attendance'])->name('attendance.index');
    Route::get('/don-hang', [MembershipOrderController::class, 'index'])->name('orders.index');
    Route::get('/don-hang/{order}', [MembershipOrderController::class, 'show'])
        ->middleware('can:view,order')
        ->name('orders.show');
    Route::post('/don-hang/{order}/cancel-request', [MembershipOrderController::class, 'requestCancellation'])
        ->middleware('can:requestCancellation,order')
        ->name('orders.cancel-request');
    Route::get('/thong-tin', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/thong-tin', [ProfileController::class, 'update'])->name('profile.update');
});

Route::post('/my/don-hang', [MembershipOrderController::class, 'store'])
    ->middleware(['auth', 'role:customer'])
    ->name('orders.store');

Route::middleware('auth')->prefix('staff')->name('staff.')->group(function (): void {
    Route::get('/yeu-cau-tap-thu', [TrialRequestController::class, 'index'])
        ->middleware('can:viewAny,'.TrialRequest::class)
        ->name('trial-requests.index');
    Route::post('/yeu-cau-tap-thu/{trialRequest}/da-lien-he', [TrialRequestController::class, 'markContacted'])
        ->middleware('can:markContacted,trialRequest')
        ->name('trial-requests.contact');

    Route::get('/don-hang', [StaffOrderController::class, 'index'])
        ->middleware('can:viewAny,'.MembershipOrder::class)
        ->name('orders.index');
    Route::post('/don-hang/{order}/payments', [StaffOrderController::class, 'recordPayment'])
        ->middleware('can:recordPayment,order')
        ->name('orders.payments.store');
    Route::post('/don-hang/{order}/confirm', [StaffOrderController::class, 'confirm'])
        ->middleware('can:confirm,order')
        ->name('orders.confirm');
    Route::post('/don-hang/{order}/cancel', [StaffOrderController::class, 'cancel'])
        ->middleware('can:cancel,order')
        ->name('orders.cancel');

    Route::get('/check-in', [StaffCheckInController::class, 'index'])
        ->middleware('can:viewAny,'.CheckIn::class)
        ->name('checkins.index');
    Route::post('/check-in', [StaffCheckInController::class, 'store'])
        ->middleware('can:checkIn,'.CheckIn::class)
        ->name('checkins.store');
    Route::post('/check-in/{visit}/checkout', [StaffCheckInController::class, 'checkOut'])
        ->middleware('can:checkOut,visit')
        ->name('checkins.checkout');
    Route::post('/check-in/{visit}/close', [StaffCheckInController::class, 'manualClose'])
        ->middleware('can:manualClose,visit')
        ->name('checkins.manual-close');
});

Route::middleware(['auth', 'role:manager'])->get('/quan-ly', [ManagerDashboardController::class, 'index'])->name('manager.dashboard');

Route::middleware(['auth', 'role:manager'])->prefix('quan-ly/goi-tap')->name('manager.packages.')->group(function (): void {
    Route::get('/', [ManagerGymPackageController::class, 'index'])
        ->middleware('can:viewAny,'.GymPackage::class)
        ->name('index');
    Route::get('/tao', [ManagerGymPackageController::class, 'create'])
        ->middleware('can:create,'.GymPackage::class)
        ->name('create');
    Route::post('/', [ManagerGymPackageController::class, 'store'])
        ->middleware('can:create,'.GymPackage::class)
        ->name('store');
    Route::get('/{package}/sua', [ManagerGymPackageController::class, 'edit'])
        ->middleware('can:update,package')
        ->name('edit');
    Route::put('/{package}', [ManagerGymPackageController::class, 'update'])
        ->middleware('can:update,package')
        ->name('update');
    Route::patch('/{package}/trang-thai', [ManagerGymPackageController::class, 'toggleActive'])
        ->middleware('can:toggleActive,package')
        ->name('toggle-active');
});
