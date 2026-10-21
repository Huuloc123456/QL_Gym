<?php

namespace App\Providers;

use App\Models\GymPackage;
use App\Models\User;
use App\Services\GymIdentityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['layouts.public-navigation', 'layouts.internal-navigation'], function ($view): void {
            $count = auth()->check() && Schema::hasTable('gym_messages') ? DB::table('gym_messages')->where('user_id', auth()->id())->whereNull('read_at')->count() : 0;
            $view->with('unreadMessages', $count);
        });
        User::created(function (User $user): void {
            if (in_array($user->role, ['customer', 'employee', 'manager'], true) && Schema::hasTable('KHACHHANG')) {
                app(GymIdentityService::class)->sync($user);
            }
        });
        GymPackage::saved(function (GymPackage $package): void {
            if (Schema::hasTable('GOITAP')) {
                app(GymIdentityService::class)->syncPackage($package);
            }
        });
        User::updated(function (User $user): void {
            if ($user->sql_customer_id || $user->sql_employee_id) {
                app(GymIdentityService::class)->sync($user);
            }
        });
    }
}
