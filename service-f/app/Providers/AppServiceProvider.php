<?php

namespace App\Providers;

use App\Contracts\Repositories\ShortLinkClickRepositoryInterface;
use App\Contracts\Repositories\ShortLinkRepositoryInterface;
use App\Contracts\UrlShortener\OriginalUrlReachabilityCheckerInterface;
use App\Contracts\UrlShortener\ShortCodeGeneratorInterface;
use App\Contracts\UrlShortener\UrlShortenerServiceInterface;
use App\Repositories\EloquentShortLinkClickRepository;
use App\Repositories\EloquentShortLinkRepository;
use App\Services\UrlShortener\HttpOriginalUrlReachabilityChecker;
use App\Services\UrlShortener\ShortCodeGenerator;
use App\Services\UrlShortener\ShortUrlBuilder;
use App\Services\UrlShortener\UrlShortenerService;
use Illuminate\Support\ServiceProvider;

/** Регистрация DI: контракты репозиториев → Eloquent-реализации. */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ShortLinkRepositoryInterface::class, EloquentShortLinkRepository::class);
        $this->app->bind(ShortLinkClickRepositoryInterface::class, EloquentShortLinkClickRepository::class);
        $this->app->bind(OriginalUrlReachabilityCheckerInterface::class, HttpOriginalUrlReachabilityChecker::class);
        $this->app->singleton(ShortUrlBuilder::class);
        $this->app->bind(ShortCodeGeneratorInterface::class, ShortCodeGenerator::class);
        $this->app->bind(UrlShortenerServiceInterface::class, UrlShortenerService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
