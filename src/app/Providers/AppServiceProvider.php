<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Ensure JSON responses for all API errors
        if ($this->app->runningInConsole() === false) {
            \Illuminate\Support\Facades\Request::macro('expectsJson', fn () => true);
        }
    }
}
