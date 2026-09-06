<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(\App\Services\GlobalSiteDataService::class);
        $this->app->scoped(\App\Services\CorporateContentService::class);
    }

    public function boot(): void {}
}
