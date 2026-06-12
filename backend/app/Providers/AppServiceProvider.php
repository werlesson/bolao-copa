<?php

namespace App\Providers;

use App\Contracts\RankingBulletinGenerator;
use App\Services\GeminiBulletinGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RankingBulletinGenerator::class, GeminiBulletinGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
