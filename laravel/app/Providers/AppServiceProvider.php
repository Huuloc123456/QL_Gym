<?php

namespace App\Providers;

use App\Models\GymSetting;
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
        View::composer('layouts.app', function ($view): void {
            $settings = null;
            if (Schema::hasTable('gym_settings')) {
                $settings = GymSetting::query()->find(1);
            }
            $view->with('gymSettings', $settings);
        });
    }
}
