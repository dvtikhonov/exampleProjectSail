<?php

namespace App\Providers;

use App\Support\SanctumStatefulDomainsResolver;
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
        if ($this->app->runningInConsole()) {
            return;
        }

        $request = request();

        config([
            'sanctum.stateful' => app(SanctumStatefulDomainsResolver::class)->resolve($request),
        ]);

        $appUrl = rtrim((string) config('app.url'), '/');
        $isHttpsRequest = $request->isSecure()
            || $request->header('X-Forwarded-Proto') === 'https';

        if (str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
            URL::forceRootUrl($appUrl);
        } elseif ($isHttpsRequest) {
            URL::forceScheme('https');
            URL::forceRootUrl('https://'.$request->getHost());
        }

        if ($isHttpsRequest && env('SESSION_SECURE_COOKIE') === null) {
            config(['session.secure' => true]);
        }
    }
}
