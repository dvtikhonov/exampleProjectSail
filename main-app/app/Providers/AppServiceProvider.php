<?php

namespace App\Providers;

use App\Services\MicroserviceHttpClient;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MicroserviceHttpClient::class, fn (): MicroserviceHttpClient => new MicroserviceHttpClient);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        if ($this->app->runningInConsole()) {
            return;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        if (str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
            URL::forceRootUrl($appUrl);

            return;
        }

        if (request()->header('X-Forwarded-Proto') === 'https') {
            URL::forceScheme('https');
            URL::forceRootUrl('https://'.request()->getHost());
        }
    }
}
