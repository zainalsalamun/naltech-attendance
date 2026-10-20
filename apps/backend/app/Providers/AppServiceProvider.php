<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Support\CompanyContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('layouts.workspace', function ($view) {
            $user = auth()->user();
            $view->with('company', $user->company()->firstOrFail());
            $view->with('manager', in_array($user->role, ['admin', 'super admin', 'hrd', 'supervisor'], true));
        });
        Paginator::useBootstrapFour();
    }
}
