<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\GymAdministrationController;
use App\Http\Controllers\GymDatabaseController;
use App\Http\Controllers\GymInvoiceController;
use App\Http\Controllers\GymPortalController;
use App\Http\Controllers\GymReportController;
use App\Http\Controllers\GymStoreController;
use App\Http\Controllers\GymTrainingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ManagerDashboardController;
use App\Http\Controllers\ManagerEmployeeController;
use App\Http\Controllers\ManagerGymPackageController;
use App\Http\Controllers\ManagerHeroBannerController;
use App\Http\Controllers\ManagerInvoiceReconciliationController;
use App\Http\Controllers\ManagerPaymentReviewController;
use App\Http\Controllers\ManagerRagKnowledgeController;
use App\Http\Controllers\ManagerRefundController;
use App\Http\Controllers\ManagerReportController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MembershipOrderController;
use App\Http\Controllers\PasswordRecoveryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaffCheckInController;
use App\Http\Controllers\StaffDashboardController;
use App\Http\Controllers\StaffOrderController;
use App\Http\Controllers\StaffProductController;
use App\Http\Controllers\TrialRequestController;
use App\Http\Controllers\VnPayController;
use App\Models\CheckIn;
use App\Models\GymPackage;
use App\Models\MembershipOrder;
use App\Models\TrialRequest;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/goi-tap', [MemberController::class, 'packages'])->name('packages.index');
Route::get('/goi-tap/{package}', [MemberController::class, 'showPackage'])->name('packages.show');
Route::post('/dang-ky-trai-nghiem', [TrialRequestController::class, 'store'])
    ->middleware(['auth', 'role:customer', 'throttle:5,1'])
    ->name('trials.store');
Route::get('/tap-thu', [TrialRequestController::class, 'create'])->middleware(['auth', 'role:customer'])->name('trials.create');
Route::post('/tap-thu/{trialRequest}/huy', [TrialRequestController::class, 'cancel'])->middleware(['auth', 'role:customer'])->name('trials.cancel');
Route::post('/chatbot/message', ChatbotController::class)
    ->middleware('throttle:10,1')
    ->name('chatbot.message');

Route::middleware('guest')->group(function (): void {
    Route::get('/dang-nhap', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/dang-nhap', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.store');
    Route::get('/dang-ky', [AuthController::class, 'showRegistration'])->name('register');
    Route::post('/dang-ky', [AuthController::class, 'register'])
        ->middleware('throttle:5,1')
        ->name('register.store');
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

Route::middleware('auth')->prefix('tai-khoan')->name('account.')->group(function (): void {
    Route::get('/doi-mat-khau', [ChangePasswordController::class, 'edit'])->name('password.edit');
    Route::put('/doi-mat-khau', [ChangePasswordController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});

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

Route::post('/my/don-hang/{order}/thanh-toan/vnpay', [VnPayController::class, 'membershipOrder'])
    ->middleware(['auth', 'role:customer', 'can:view,order'])
    ->name('my.orders.vnpay');

Route::get('/thanh-toan/vnpay/ipn', [VnPayController::class, 'ipn'])->name('vnpay.ipn');
Route::get('/thanh-toan/vnpay/ket-qua', [VnPayController::class, 'returned'])->name('vnpay.return');

Route::middleware(['auth', 'role:employee,manager'])->prefix('staff')->name('staff.')->group(function (): void {
    Route::get('/', StaffDashboardController::class)->name('dashboard');

    Route::get('/san-pham', [StaffProductController::class, 'index'])->name('products.index');
    Route::get('/san-pham/them', [StaffProductController::class, 'create'])->name('products.create');
    Route::get('/san-pham/{product}/sua', [StaffProductController::class, 'edit'])->whereNumber('product')->name('products.edit');
    Route::post('/san-pham', [StaffProductController::class, 'store'])->name('products.store');
    Route::post('/san-pham/{product}', [StaffProductController::class, 'update'])->whereNumber('product')->name('products.update');
    Route::post('/san-pham/{product}/anh/{image?}', [StaffProductController::class, 'image'])->whereNumber('product')->whereNumber('image')->name('products.image');

    Route::get('/yeu-cau-tap-thu', [TrialRequestController::class, 'index'])
        ->middleware('can:viewAny,'.TrialRequest::class)
        ->name('trial-requests.index');
    Route::post('/yeu-cau-tap-thu/{trialRequest}/da-lien-he', [TrialRequestController::class, 'markContacted'])
        ->middleware('can:markContacted,trialRequest')
        ->name('trial-requests.contact');
    Route::post('/yeu-cau-tap-thu/{trialRequest}/gan-tai-khoan', [TrialRequestController::class, 'linkLegacyAccount'])
        ->middleware('role:manager')
        ->name('trial-requests.link-account');

    Route::get('/don-hang', [StaffOrderController::class, 'index'])
        ->middleware('can:viewAny,'.MembershipOrder::class)
        ->name('orders.index');
    Route::get('/don-hang/{order}', [StaffOrderController::class, 'show'])
        ->middleware('can:viewAny,'.MembershipOrder::class)
        ->name('orders.show');
    Route::post('/don-hang/{order}/payments', [StaffOrderController::class, 'recordPayment'])
        ->middleware('can:recordPayment,order')
        ->name('orders.payments.store');
    Route::post('/don-hang/{order}/confirm', [StaffOrderController::class, 'confirm'])
        ->middleware('can:confirm,order')
        ->name('orders.confirm');
    Route::post('/don-hang/{order}/cancel', [StaffOrderController::class, 'cancel'])
        ->middleware('can:cancel,order')
        ->name('orders.cancel');
    Route::post('/don-hang/{order}/tiep-tuc', [StaffOrderController::class, 'resumeCancellation'])
        ->middleware('can:resumeCancellation,order')
        ->name('orders.resume-cancellation');

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

Route::middleware(['auth', 'role:manager'])->prefix('quan-ly')->name('manager.')->group(function (): void {
    Route::post('hoan-tien', [ManagerRefundController::class, 'store'])->name('refunds.store');
    Route::get('bao-cao', [GymReportController::class, 'index'])->name('reports.index');
    Route::get('bao-cao/xuat-excel', [ManagerReportController::class, 'export'])->name('reports.export');
    Route::get('doi-soat-thanh-toan', [ManagerPaymentReviewController::class, 'index'])->name('payment-reviews.index');
    Route::post('doi-soat-thanh-toan/{payment}/xac-nhan', [ManagerPaymentReviewController::class, 'confirm'])->whereNumber('payment')->name('payment-reviews.confirm');
    Route::post('doi-soat-thanh-toan/{payment}/hoan-tien', [ManagerPaymentReviewController::class, 'refund'])->whereNumber('payment')->name('payment-reviews.refund');

    Route::get('cai-dat', [GymPortalController::class, 'settings'])->name('settings.edit');
    Route::put('cai-dat', [GymPortalController::class, 'saveSettings'])->name('settings.update');
    Route::put('doi-soat-hoa-don/{invoice}', [ManagerInvoiceReconciliationController::class, 'update'])->whereNumber('invoice')->name('reconciliations.update');
    Route::get('banner', [ManagerHeroBannerController::class, 'index'])->name('banners.index');
    Route::post('banner', [ManagerHeroBannerController::class, 'store'])->name('banners.store');
    Route::put('banner/{banner}', [ManagerHeroBannerController::class, 'update'])->name('banners.update');
    Route::patch('banner/{banner}/trang-thai', [ManagerHeroBannerController::class, 'toggle'])->name('banners.toggle');
    Route::patch('banner-thu-tu', [ManagerHeroBannerController::class, 'reorder'])->name('banners.reorder');
    Route::delete('banner/{banner}', [ManagerHeroBannerController::class, 'destroy'])->name('banners.destroy');

    Route::get('tai-lieu-chatbot', [ManagerRagKnowledgeController::class, 'index'])->name('knowledge.index');
    Route::post('tai-lieu-chatbot/dong-bo', [ManagerRagKnowledgeController::class, 'sync'])->name('knowledge.sync');
    Route::get('tai-lieu-chatbot/tao-moi', [ManagerRagKnowledgeController::class, 'create'])->name('knowledge.create');
    Route::get('tai-lieu-chatbot/faq/tao-moi', [ManagerRagKnowledgeController::class, 'createFaq'])->name('knowledge.faq.create');
    Route::post('tai-lieu-chatbot/faq', [ManagerRagKnowledgeController::class, 'storeFaq'])->name('knowledge.faq.store');
    Route::put('tai-lieu-chatbot/{document}/faq', [ManagerRagKnowledgeController::class, 'updateFaq'])->name('knowledge.faq.update');
    Route::get('tai-lieu-chatbot/mau-excel', [ManagerRagKnowledgeController::class, 'downloadTemplate'])->name('knowledge.import.template');
    Route::post('tai-lieu-chatbot/excel/xem-truoc', [ManagerRagKnowledgeController::class, 'previewImport'])->name('knowledge.import.preview');
    Route::get('tai-lieu-chatbot/excel/xem-truoc', [ManagerRagKnowledgeController::class, 'importReview'])->name('knowledge.import.review');
    Route::post('tai-lieu-chatbot/excel/xac-nhan', [ManagerRagKnowledgeController::class, 'confirmImport'])->name('knowledge.import.confirm');
    Route::post('tai-lieu-chatbot', [ManagerRagKnowledgeController::class, 'store'])->name('knowledge.store');
    Route::get('tai-lieu-chatbot/{document}/sua', [ManagerRagKnowledgeController::class, 'edit'])->name('knowledge.edit');
    Route::put('tai-lieu-chatbot/{document}', [ManagerRagKnowledgeController::class, 'update'])->name('knowledge.update');
    Route::patch('tai-lieu-chatbot/{document}/trang-thai', [ManagerRagKnowledgeController::class, 'toggle'])->name('knowledge.toggle');
    Route::delete('tai-lieu-chatbot/{document}', [ManagerRagKnowledgeController::class, 'destroy'])->name('knowledge.destroy');

    Route::resource('goi-tap', ManagerGymPackageController::class)
        ->only(['index', 'create', 'store', 'edit', 'update'])
        ->parameters(['goi-tap' => 'package'])
        ->names([
            'index' => 'packages.index',
            'create' => 'packages.create',
            'store' => 'packages.store',
            'edit' => 'packages.edit',
            'update' => 'packages.update',
        ])
        ->middlewareFor('index', 'can:viewAny,'.GymPackage::class)
        ->middlewareFor(['create', 'store'], 'can:create,'.GymPackage::class)
        ->middlewareFor(['edit', 'update'], 'can:update,package');

    Route::patch('goi-tap/{package}/trang-thai', [ManagerGymPackageController::class, 'toggleActive'])
        ->middleware('can:toggleActive,package')
        ->name('packages.toggle-active');

    Route::resource('nhan-vien', ManagerEmployeeController::class)
        ->only(['index', 'create', 'store', 'edit', 'update'])
        ->parameters(['nhan-vien' => 'employee'])
        ->names([
            'index' => 'employees.index',
            'create' => 'employees.create',
            'store' => 'employees.store',
            'edit' => 'employees.edit',
            'update' => 'employees.update',
        ])
        ->middlewareFor('index', 'can:viewAny,'.User::class)
        ->middlewareFor(['create', 'store'], 'can:create,'.User::class)
        ->middlewareFor(['edit', 'update'], 'can:update,employee');

    Route::get('nhan-vien/{employee}/phan-quyen', [ManagerEmployeeController::class, 'editPermissions'])
        ->middleware('can:update,employee')
        ->name('employees.permissions.edit');
    Route::put('nhan-vien/{employee}/phan-quyen', [ManagerEmployeeController::class, 'updatePermissions'])
        ->middleware('can:update,employee')
        ->name('employees.permissions.update');
    Route::patch('nhan-vien/{employee}/trang-thai', [ManagerEmployeeController::class, 'toggleActive'])
        ->middleware('can:update,employee')
        ->name('employees.toggle-active');
});

Route::get('/san-pham', [GymStoreController::class, 'index'])->name('gym.products.index');
Route::get('/lien-he', [GymPortalController::class, 'info'])->name('gym.contact');
Route::get('/huong-dan', [GymPortalController::class, 'info'])->name('gym.help');
Route::get('/chinh-sach', [GymPortalController::class, 'info'])->name('gym.policy');
Route::middleware('auth')->group(function (): void {
    Route::get('/thong-bao', [GymPortalController::class, 'messages'])->name('gym.messages');
    Route::post('/thong-bao/doc-tat-ca', [GymPortalController::class, 'readAll'])->name('gym.messages.read-all');
    Route::post('/thong-bao/{message}/doc', [GymPortalController::class, 'read'])->whereNumber('message')->name('gym.messages.read');
    Route::put('/tai-khoan/mat-khau', [ProfileController::class, 'password'])->middleware('throttle:5,1')->name('gym.password');
    Route::get('/tai-khoan/bao-mat', fn () => view('profile.password'))->name('gym.security');
});
Route::middleware(['auth', 'role:manager'])->group(function (): void {
    Route::get('/quan-ly/cau-hinh', fn () => redirect()->route('manager.settings.edit'))->name('gym.settings');
    Route::put('/quan-ly/cau-hinh', [GymPortalController::class, 'saveSettings'])->name('gym.settings.save');
    Route::get('/quan-ly/bao-cao/csv', [GymReportController::class, 'export'])->name('gym.reports.export');
});
Route::post('/staff/yeu-cau-tap-thu/{trialRequest}/duyet', [TrialRequestController::class, 'approve'])->middleware(['auth', 'role:employee,manager', 'can:approve,trialRequest'])->name('staff.trial-requests.approve');
Route::post('/staff/yeu-cau-tap-thu/{trialRequest}/tu-choi', [TrialRequestController::class, 'reject'])->middleware(['auth', 'role:employee,manager', 'can:approve,trialRequest'])->name('staff.trial-requests.reject');
Route::post('/staff/yeu-cau-tap-thu/{trialRequest}/doi-lich', [TrialRequestController::class, 'reschedule'])->middleware(['auth', 'role:employee,manager', 'can:reschedule,trialRequest'])->name('staff.trial-requests.reschedule');
Route::post('/staff/yeu-cau-tap-thu/{trialRequest}/thu-hoi', [TrialRequestController::class, 'revoke'])->middleware(['auth', 'role:employee,manager', 'can:revoke,trialRequest'])->name('staff.trial-requests.revoke');
Route::get('/san-pham/{product}', [GymStoreController::class, 'show'])->whereNumber('product')->name('gym.products.show');
Route::get('/lop-hoc', [GymTrainingController::class, 'classes'])->name('gym.classes.index');
Route::get('/lop-hoc/{class}', [GymTrainingController::class, 'showClass'])->whereNumber('class')->name('gym.classes.show');

Route::middleware(['auth', 'role:customer'])->group(function (): void {
    Route::get('/gio-hang', [GymStoreController::class, 'cart'])->name('gym.cart');
    Route::post('/gio-hang', [GymStoreController::class, 'add'])->name('gym.cart.add');
    Route::post('/gio-hang/{line}', [GymStoreController::class, 'change'])->whereNumber('line')->name('gym.cart.change');
    Route::post('/dat-hang', [GymStoreController::class, 'checkout'])->name('gym.checkout');
    Route::post('/hoa-don/{invoice}/thanh-toan/vnpay', [VnPayController::class, 'invoice'])->whereNumber('invoice')->name('gym.invoices.vnpay');
    Route::get('/my/dia-chi', [GymStoreController::class, 'addresses'])->name('gym.addresses');
    Route::post('/my/dia-chi/{address?}', [GymStoreController::class, 'addressSave'])->whereNumber('address')->name('gym.addresses.save');
    Route::delete('/my/dia-chi/{address}', [GymStoreController::class, 'addressDelete'])->whereNumber('address')->name('gym.addresses.delete');
    Route::get('/my/lich-su-dang-nhap', [GymStoreController::class, 'loginHistory'])->name('gym.login-history');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/hoa-don', [GymInvoiceController::class, 'index'])->name('gym.invoices.index');
    Route::get('/hoa-don/{invoice}', [GymInvoiceController::class, 'show'])->whereNumber('invoice')->name('gym.invoices.show');
    Route::post('/hoa-don/{invoice}/thu-tien', [GymInvoiceController::class, 'receive'])->name('gym.invoices.receive');
    Route::post('/hoa-don/{invoice}/xac-nhan', [GymInvoiceController::class, 'confirm'])->name('gym.invoices.confirm');
    Route::post('/hoa-don/{invoice}/huy', [GymInvoiceController::class, 'cancel'])->name('gym.invoices.cancel');
    Route::post('/hoa-don/{invoice}/tiep-tuc', [GymInvoiceController::class, 'resumeCancellation'])->name('gym.invoices.resume-cancellation');
    Route::post('/hoa-don/{invoice}/giao-nhan', [GymInvoiceController::class, 'fulfill'])->name('gym.invoices.fulfill');
    Route::post('/hoa-don/{invoice}/ky', [GymInvoiceController::class, 'sign'])->name('gym.invoices.sign');
    Route::post('/hoa-don/{invoice}/kiem-tra', [GymInvoiceController::class, 'verify'])->middleware('throttle:10,1')->name('gym.invoices.verify');
    Route::get('/hoa-don/{invoice}/tai', [GymInvoiceController::class, 'download'])->name('gym.invoices.download');
    Route::get('/lich-tap', [GymTrainingController::class, 'calendar'])->name('gym.calendar');
    Route::post('/lich-tap/lop/{class}', [GymTrainingController::class, 'classSession'])->name('gym.class.session');
    Route::post('/lich-tap/lop/{session}/hoan-thanh', [GymTrainingController::class, 'finish'])->name('gym.sessions.finish');
    Route::delete('/lich-tap/lop/{session}', [GymTrainingController::class, 'remove'])->name('gym.sessions.remove');
});

Route::middleware(['auth', 'role:manager'])->prefix('quan-ly/danh-muc')->name('gym.admin.')->group(function (): void {
    Route::get('/khach-hang/{customer}', [GymAdministrationController::class, 'showCustomer'])->whereNumber('customer')->name('customers.show');
    Route::post('/khach-hang/{customer}/xac-minh-hang', [GymAdministrationController::class, 'verifyCustomerTier'])->whereNumber('customer')->name('customers.verify-tier');
    Route::get('/{resource}', [GymAdministrationController::class, 'index'])->name('index');
    Route::get('/{resource}/them', [GymAdministrationController::class, 'edit'])->name('create');
    Route::get('/{resource}/{record}/sua', [GymAdministrationController::class, 'edit'])->whereNumber('record')->name('edit');
    Route::post('/{resource}', [GymAdministrationController::class, 'save'])->name('store');
    Route::post('/{resource}/{record}', [GymAdministrationController::class, 'save'])->whereNumber('record')->name('save');
    Route::delete('/{resource}/{record}', [GymAdministrationController::class, 'destroy'])->whereNumber('record')->name('destroy');
});
Route::middleware(['auth', 'role:manager'])->post('/quan-ly/san-pham/{product}/anh/{image?}', [GymAdministrationController::class, 'image'])->name('gym.admin.image');
Route::middleware(['auth', 'role:manager'])->post('/quan-ly/dang-ky/{kind}/{registration}/huy', [GymTrainingController::class, 'end'])->where('kind', 'package|class')->name('gym.registration.end');
Route::middleware(['auth', 'role:manager'])->prefix('quan-ly/csdl')->name('gym.database.')->group(function (): void {
    Route::get('/', [GymDatabaseController::class, 'index'])->name('index');
    Route::get('/bang/{table}', [GymDatabaseController::class, 'table'])->name('table');
    Route::get('/nhat-ky', [GymDatabaseController::class, 'audit'])->name('audit');
    Route::get('/tai-khoan', [GymDatabaseController::class, 'accounts'])->name('accounts');
    Route::post('/tai-khoan', [GymDatabaseController::class, 'accountAction'])->middleware('throttle:10,1')->name('account-action');
});
