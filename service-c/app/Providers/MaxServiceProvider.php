<?php

namespace App\Providers;

use App\Contracts\Max\AuthenticatedMaxUserResolverInterface;
use App\Contracts\Max\MaxAdminBotTestSenderInterface;
use App\Contracts\Max\MaxAiAccessServiceInterface;
use App\Contracts\Max\MaxCallbackHandlerInterface;
use App\Contracts\Max\MaxLoadTestDataRepositoryInterface;
use App\Contracts\Max\MaxLoadTestServiceInterface;
use App\Contracts\Max\MaxLoadTestUserRepositoryInterface;
use App\Contracts\Max\MaxManagerDailyMenuNotifierInterface;
use App\Contracts\Max\MaxMenuAvailabilityNotifierInterface;
use App\Contracts\Max\MaxMessengerNotificationSenderInterface;
use App\Contracts\Max\MaxMiniAppAccessLoggerInterface;
use App\Contracts\Max\MaxMiniAppAuthServiceInterface;
use App\Contracts\Max\MaxMiniAppTokenIssuerInterface;
use App\Contracts\Max\MaxOrderNotificationConfigProviderInterface;
use App\Contracts\Max\MaxUiStandGreetingSenderInterface;
use App\Contracts\Max\MaxUiStandRecipientRegistryInterface;
use App\Contracts\Max\MaxUiStandRecipientResolverInterface;
use App\Contracts\Max\MaxUserAiAccessRepositoryInterface;
use App\Contracts\Max\MaxUserDeliveryAddressInterface;
use App\Contracts\Max\MaxUserDeliveryRepositoryInterface;
use App\Contracts\Max\MaxUserIdentityRepositoryInterface;
use App\Contracts\Max\MaxUserManualOrderQueryRepositoryInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\Contracts\Max\MaxWebAppInitDataValidatorInterface;
use App\Contracts\Max\MaxWebhookStaleDevTunnelCleanerInterface;
use App\Contracts\Max\MaxWebhookSubscriberInterface;
use App\Contracts\Max\MaxWebhookSubscriptionClientInterface;
use App\Contracts\Max\MaxWebhookUpdateRouterInterface;
use App\Contracts\Max\MaxWebhookUrlProbeInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\Enums\Max\MaxWebhookUpdateType;
use App\Http\Controllers\Api\MaxWebhookController;
use App\Http\Resolvers\AuthenticatedMaxUserResolver;
use App\Infrastructure\Laravel\LaravelFoodOrderManualCreatorNotifier;
use App\Infrastructure\Laravel\LaravelFoodOrderMaxNotifier;
use App\Infrastructure\Laravel\LaravelMaxAdminBotTestSender;
use App\Infrastructure\Laravel\LaravelMaxMiniAppAccessLogger;
use App\Infrastructure\Laravel\LaravelMaxMiniAppTokenIssuer;
use App\Infrastructure\Laravel\LaravelMaxUiStandRecipientRegistry;
use App\Infrastructure\Laravel\LaravelMaxUiStandRecipientResolver;
use App\Infrastructure\Laravel\LaravelOrderChatNotifier;
use App\Infrastructure\Laravel\MaxWebhookSubscriptionClient;
use App\Repositories\Max\EloquentMaxLoadTestDataRepository;
use App\Repositories\Max\EloquentMaxLoadTestUserRepository;
use App\Repositories\Max\EloquentMaxUserAiAccessRepository;
use App\Repositories\Max\EloquentMaxUserDeliveryRepository;
use App\Repositories\Max\EloquentMaxUserIdentityRepository;
use App\Repositories\Max\EloquentMaxUserManualOrderQueryRepository;
use App\Repositories\Max\EloquentMaxUserRepository;
use App\Services\Max\CachingMaxAiAccessService;
use App\Services\Max\ConfigMaxMessengerRetryConfigFactory;
use App\Services\Max\ConfigMaxOrderNotificationConfigProvider;
use App\Services\Max\EnvMaxBotTokenProvider;
use App\Services\Max\MaxAiAccessService;
use App\Services\Max\MaxLoadTestService;
use App\Services\Max\MaxMessengerNotificationSender;
use App\Services\Max\MaxMiniAppAuthService;
use App\Services\Max\MaxUserDeliveryAddressService;
use App\Services\Max\MaxWebAppInitDataValidator;
use App\Services\Max\UiStand\BotStartedUpdateHandler;
use App\Services\Max\UiStand\MaxCallbackHandler;
use App\Services\Max\UiStand\MaxManagerDailyMenuNotifier;
use App\Services\Max\UiStand\MaxMenuAvailabilityNotifier;
use App\Services\Max\UiStand\MaxUiStandGreetingSender;
use App\Services\Max\UiStand\MaxWebhookStaleDevTunnelCleaner;
use App\Services\Max\UiStand\MaxWebhookSubscriber;
use App\Services\Max\UiStand\MaxWebhookUpdateRouter;
use App\Services\Max\UiStand\MaxWebhookUrlProbe;
use App\Services\Max\UiStand\MessageCallbackUpdateHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Shared\MaxMessenger\Client\HttpMaxMessengerClient;
use Shared\MaxMessenger\Client\NullMaxMessengerClient;
use Shared\MaxMessenger\Contracts\MaxBotTokenProviderInterface;
use Shared\MaxMessenger\Contracts\MaxMessengerClientInterface;

/**
 * DI-привязки Max mini-app, webhook и уведомлений.
 */
class MaxServiceProvider extends ServiceProvider
{
    /**
     * Регистрирует Max-контракты и сервисы в контейнере.
     */
    public function register(): void
    {
        $this->app->bind(MaxMiniAppTokenIssuerInterface::class, LaravelMaxMiniAppTokenIssuer::class);
        $this->app->bind(MaxMiniAppAccessLoggerInterface::class, LaravelMaxMiniAppAccessLogger::class);
        $this->app->bind(MaxLoadTestDataRepositoryInterface::class, EloquentMaxLoadTestDataRepository::class);
        $this->app->bind(MaxAdminBotTestSenderInterface::class, LaravelMaxAdminBotTestSender::class);
        $this->app->bind(MaxLoadTestServiceInterface::class, MaxLoadTestService::class);
        $this->app->bind(AuthenticatedMaxUserResolverInterface::class, AuthenticatedMaxUserResolver::class);
        $this->app->bind(MaxUserDeliveryAddressInterface::class, MaxUserDeliveryAddressService::class);
        $this->app->bind(MaxUserIdentityRepositoryInterface::class, EloquentMaxUserIdentityRepository::class);
        $this->app->bind(MaxUserDeliveryRepositoryInterface::class, EloquentMaxUserDeliveryRepository::class);
        $this->app->bind(MaxUserAiAccessRepositoryInterface::class, EloquentMaxUserAiAccessRepository::class);
        $this->app->bind(MaxUserManualOrderQueryRepositoryInterface::class, EloquentMaxUserManualOrderQueryRepository::class);
        $this->app->bind(MaxLoadTestUserRepositoryInterface::class, EloquentMaxLoadTestUserRepository::class);
        $this->app->bind(MaxUserRepositoryInterface::class, EloquentMaxUserRepository::class);
        $this->app->bind(
            MaxAiAccessServiceInterface::class,
            function ($app): CachingMaxAiAccessService {
                return new CachingMaxAiAccessService(
                    $app->make(MaxAiAccessService::class),
                    $app->make(CacheStoreInterface::class),
                    (bool) config('max.ai_access_cache_enabled', true),
                );
            },
        );
        $this->app->bind(MaxMenuAvailabilityNotifierInterface::class, MaxMenuAvailabilityNotifier::class);
        $this->app->bind(
            MaxMessengerNotificationSenderInterface::class,
            MaxMessengerNotificationSender::class,
        );
        $this->app->bind(MaxManagerDailyMenuNotifierInterface::class, MaxManagerDailyMenuNotifier::class);
        $this->app->bind(MaxBotTokenProviderInterface::class, EnvMaxBotTokenProvider::class);
        $this->app->bind(MaxMessengerClientInterface::class, function ($app): MaxMessengerClientInterface {
            // Laravel: MAX_MESSENGER_DRIVER=null в .env → PHP null; учитываем null / '' / 'null'.
            $driver = config('max.messenger_driver', 'http');
            if ($driver === null || $driver === '' || $driver === 'null') {
                return new NullMaxMessengerClient;
            }

            return new HttpMaxMessengerClient(
                tokenProvider: $app->make(MaxBotTokenProviderInterface::class),
                retryConfig: $app->make(ConfigMaxMessengerRetryConfigFactory::class)->make(),
            );
        });
        $this->app->bind(MaxWebhookUpdateRouterInterface::class, static function ($app): MaxWebhookUpdateRouter {
            return new MaxWebhookUpdateRouter(
                [
                    MaxWebhookUpdateType::MessageCallback->value => $app->make(MessageCallbackUpdateHandler::class),
                    MaxWebhookUpdateType::BotStarted->value => $app->make(BotStartedUpdateHandler::class),
                ],
                Log::channel('max_log'),
            );
        });
        $this->app->bind(MaxWebhookUpdateRouter::class, static function ($app): MaxWebhookUpdateRouter {
            return $app->make(MaxWebhookUpdateRouterInterface::class);
        });
        $this->app->bind(MaxWebhookSubscriptionClientInterface::class, MaxWebhookSubscriptionClient::class);
        $this->app->bind(MaxWebhookUrlProbeInterface::class, MaxWebhookUrlProbe::class);
        $this->app->bind(MaxWebhookStaleDevTunnelCleanerInterface::class, MaxWebhookStaleDevTunnelCleaner::class);
        $this->app->bind(MaxWebhookSubscriberInterface::class, MaxWebhookSubscriber::class);
        $this->app->bind(MaxCallbackHandlerInterface::class, MaxCallbackHandler::class);
        $this->app->bind(MaxUiStandGreetingSenderInterface::class, MaxUiStandGreetingSender::class);
        $this->app->bind(
            MaxUiStandRecipientRegistryInterface::class,
            LaravelMaxUiStandRecipientRegistry::class,
        );
        $this->app->bind(MaxWebAppInitDataValidatorInterface::class, MaxWebAppInitDataValidator::class);
        $this->app->bind(MaxMiniAppAuthServiceInterface::class, MaxMiniAppAuthService::class);
        $this->app->bind(
            MaxUiStandRecipientResolverInterface::class,
            LaravelMaxUiStandRecipientResolver::class,
        );
        $this->app->bind(
            MaxOrderNotificationConfigProviderInterface::class,
            ConfigMaxOrderNotificationConfigProvider::class,
        );
        $this->app->when([
            MaxCallbackHandler::class,
            MaxMenuAvailabilityNotifier::class,
            MaxManagerDailyMenuNotifier::class,
            BotStartedUpdateHandler::class,
            MaxMessengerNotificationSender::class,
            MaxWebhookSubscriptionClient::class,
            LaravelMaxMiniAppAccessLogger::class,
            MaxWebhookController::class,
            LaravelOrderChatNotifier::class,
            LaravelFoodOrderMaxNotifier::class,
            LaravelFoodOrderManualCreatorNotifier::class,
            LaravelMaxAdminBotTestSender::class,
        ])
            ->needs(LoggerInterface::class)
            ->give(static fn (): LoggerInterface => Log::channel('max_log'));
    }
}
