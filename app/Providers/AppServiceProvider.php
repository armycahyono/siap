<?php

namespace App\Providers;

use App\Models\PiketIncident;
use App\Policies\PiketIncidentPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        Gate::policy(PiketIncident::class, PiketIncidentPolicy::class);

        Paginator::defaultView('vendor.pagination.tabler');

        if (request()->header('x-forwarded-proto') === 'https' || request()->isSecure() || str_ends_with(request()->getHost(), 'sudahonline.my.id')) {
            URL::forceScheme('https');
        }
    }
}
