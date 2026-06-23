<?php

namespace App\Providers;

use App\Contracts\Auth\GatewayAuthSessionInterface;
use App\Contracts\Auth\GatewayUserContextInterface;
use App\Contracts\Auth\GatewayUserResolverInterface;
use App\Contracts\Food\CustomerCategoryRepositoryInterface;
use App\Contracts\Food\DeliveryTierRepositoryInterface;
use App\Contracts\Food\DishImageDeliveryInterface;
use App\Contracts\Food\DishImageUrlResolverInterface;
use App\Contracts\Food\FoodOrderMaxNotifierInterface;
use App\Contracts\Max\MaxOrderNotificationConfigProviderInterface;
use App\Contracts\Max\MaxWebAppInitDataValidatorInterface;
use App\Contracts\Max\MaxWebhookUpdateRouterInterface;
use App\Repositories\Food\EloquentCustomerCategoryRepository;
use App\Repositories\Food\EloquentDeliveryTierRepository;
use App\Services\Auth\EloquentGatewayUserResolver;
use App\Services\Auth\LaravelGatewayAuthSession;
use App\Services\Auth\RequestGatewayUserContext;
use App\Services\Food\DishImageDeliveryService;
use App\Services\Food\DishImageUrlResolver;
use App\Services\Food\LaravelFoodOrderMaxNotifier;
use App\Services\Max\ConfigMaxMessengerRetryConfigFactory;
use App\Services\Max\ConfigMaxOrderNotificationConfigProvider;
use App\Services\Max\EnvMaxBotTokenProvider;
use App\Services\Max\MaxWebAppInitDataValidator;
use App\Services\Max\UiStand\MaxWebhookUpdateRouter;
use App\Support\MaxAppRequestContext;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Shared\MaxMessenger\Client\HttpMaxMessengerClient;
use Shared\MaxMessenger\Contracts\MaxBotTokenProviderInterface;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;

/**
 * Регистрация DI-привязок и настройка публичных URL приложения.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Регистрирует контракты и сервисы в контейнере.
     */
    public function register(): void
    {
        $this->app->bind(GatewayUserContextInterface::class, RequestGatewayUserContext::class);
        $this->app->bind(GatewayUserResolverInterface::class, EloquentGatewayUserResolver::class);
        $this->app->bind(GatewayAuthSessionInterface::class, LaravelGatewayAuthSession::class);
        $this->app->bind(DishImageUrlResolverInterface::class, DishImageUrlResolver::class);
        $this->app->bind(DishImageDeliveryInterface::class, DishImageDeliveryService::class);
        $this->app->bind(
            DeliveryTierRepositoryInterface::class,
            EloquentDeliveryTierRepository::class,
        );
        $this->app->bind(
            CustomerCategoryRepositoryInterface::class,
            EloquentCustomerCategoryRepository::class,
        );

        $this->app->bind(MaxBotTokenProviderInterface::class, EnvMaxBotTokenProvider::class);
        $this->app->bind(MaxMessengerClientInterface::class, function ($app): HttpMaxMessengerClient {
            return new HttpMaxMessengerClient(
                tokenProvider: $app->make(MaxBotTokenProviderInterface::class),
                retryConfig: $app->make(ConfigMaxMessengerRetryConfigFactory::class)->make(),
            );
        });
        $this->app->bind(MaxWebhookUpdateRouterInterface::class, MaxWebhookUpdateRouter::class);
        $this->app->bind(MaxWebAppInitDataValidatorInterface::class, MaxWebAppInitDataValidator::class);
        $this->app->bind(
            MaxOrderNotificationConfigProviderInterface::class,
            ConfigMaxOrderNotificationConfigProvider::class,
        );
        $this->app->bind(FoodOrderMaxNotifierInterface::class, LaravelFoodOrderMaxNotifier::class);
    }

    /**
     * Настраивает схему и корневой URL для HTTPS-туннеля и прокси.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole() && MaxAppRequestContext::isPublicTunnelRequest()) {
            $publicUrl = MaxAppRequestContext::publicAppUrl();

            if ($publicUrl !== null) {
                URL::forceScheme('https');
                URL::forceRootUrl($publicUrl);

                return;
            }
        }

        $appUrl = (string) config('app.url');

        if (str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');

            // APP_URL указывает на публичный HTTPS-туннель — генерируем asset/API URL
            // от него, а не от https://127.0.0.1:8083 при локальном curl/прокси.
            if (! $this->app->runningInConsole()) {
                URL::forceRootUrl(rtrim($appUrl, '/'));
            }

            return;
        }

        if (! $this->app->runningInConsole()
            && request()->header('X-Forwarded-Proto') === 'https'
        ) {
            URL::forceScheme('https');
        }
    }
}
