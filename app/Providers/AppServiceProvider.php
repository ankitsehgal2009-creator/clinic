<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Wrong-password limit per person, so staff behind one clinic internet connection don't block each other.
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(10)->by(strtolower(trim((string) $r->input('login'))).'|'.$r->ip()));
        RateLimiter::for('setup', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
    }
}
