<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Telegram\TelegramMessengerClientInterface;
use App\Contracts\Telegram\TelegramMessengerNotificationSenderInterface;
use App\Contracts\Telegram\TelegramMiniAppAuthServiceInterface;
use App\Contracts\Telegram\TelegramWebAppInitDataValidatorInterface;
use App\Infrastructure\Laravel\HttpTelegramMessengerClient;
use App\Infrastructure\Laravel\NullTelegramMessengerClient;
use App\Services\Telegram\TelegramMessengerNotificationSender;
use App\Services\Telegram\TelegramMiniAppAuthService;
use App\Services\Telegram\TelegramWebAppInitDataValidator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * DI-привязки Telegram Mini App (auth / initData / Bot API notify).
 */
class TelegramServiceProvider extends ServiceProvider
{
    /**
     * Регистрирует Telegram-контракты и сервисы в контейнере.
     */
    public function register(): void
    {
        $this->app->bind(
            TelegramWebAppInitDataValidatorInterface::class,
            TelegramWebAppInitDataValidator::class,
        );
        $this->app->bind(
            TelegramMiniAppAuthServiceInterface::class,
            TelegramMiniAppAuthService::class,
        );
        $this->app->bind(
            TelegramMessengerClientInterface::class,
            function ($app): TelegramMessengerClientInterface {
                $token = trim((string) config('telegram.bot_token', ''));

                if ($token === '') {
                    return new NullTelegramMessengerClient;
                }

                return $app->make(HttpTelegramMessengerClient::class);
            },
        );
        $this->app->bind(
            TelegramMessengerNotificationSenderInterface::class,
            TelegramMessengerNotificationSender::class,
        );
        $this->app->when([
            TelegramMessengerNotificationSender::class,
        ])
            ->needs(LoggerInterface::class)
            ->give(static fn (): LoggerInterface => Log::channel('max_log'));
    }
}
