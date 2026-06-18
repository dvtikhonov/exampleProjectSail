<?php

namespace App\Providers;

use App\Clients\PlaywrightYandexMapsClient;
use App\Contracts\OrganizationCandidateBuilderInterface;
use App\Contracts\OrganizationRepositoryInterface;
use App\Contracts\OrganizationReviewRepositoryInterface;
use App\Contracts\YandexMapsClientInterface;
use App\Repositories\Organization\EloquentOrganizationRepository;
use App\Repositories\Organization\EloquentOrganizationReviewRepository;
use App\Services\YandexMaps\Parsing\OrganizationCandidateBuilder;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OrganizationRepositoryInterface::class, EloquentOrganizationRepository::class);
        $this->app->bind(OrganizationReviewRepositoryInterface::class, EloquentOrganizationReviewRepository::class);
        $this->app->bind(OrganizationCandidateBuilderInterface::class, OrganizationCandidateBuilder::class);

        $this->app->bind(YandexMapsClientInterface::class, function (): PlaywrightYandexMapsClient {
            return new PlaywrightYandexMapsClient(
                baseUrl: (string) config('services.yandex_parser.url'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
