<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\BlockedIp;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Click;
use App\Models\Coupon;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use Illuminate\Support\Facades\Schema;
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
        Schema::defaultStringLength(191);

        // Audit log observers
        Brand::observe(ActivityLogObserver::class);
        Campaign::observe(ActivityLogObserver::class);
        Category::observe(ActivityLogObserver::class);
        User::observe(ActivityLogObserver::class);
        Coupon::observe(ActivityLogObserver::class);
        Click::observe(ActivityLogObserver::class);
        Asset::observe(ActivityLogObserver::class);
        BlockedIp::observe(ActivityLogObserver::class);
    }
}
