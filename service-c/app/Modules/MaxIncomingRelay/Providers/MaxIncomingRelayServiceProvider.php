<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Providers;

use App\Modules\MaxIncomingRelay\Contracts\CustomerLastOrderRepositoryInterface;
use App\Modules\MaxIncomingRelay\Contracts\IncomingMessageRelayServiceInterface;
use App\Modules\MaxIncomingRelay\Repositories\EloquentCustomerLastOrderRepository;
use App\Modules\MaxIncomingRelay\Services\IncomingMessageNotificationBuilder;
use App\Modules\MaxIncomingRelay\Services\IncomingMessageRelayService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * DI-привязки модуля пересылки входящих сообщений боту (MaxIncomingRelay).
 */
class MaxIncomingRelayServiceProvider extends ServiceProvider
{
    /**
     * Регистрирует контракты и реализации модуля MaxIncomingRelay.
     */
    public function register(): void
    {
        $this->app->bind(
            CustomerLastOrderRepositoryInterface::class,
            EloquentCustomerLastOrderRepository::class,
        );
        $this->app->singleton(IncomingMessageNotificationBuilder::class);
        $this->app->bind(
            IncomingMessageRelayServiceInterface::class,
            IncomingMessageRelayService::class,
        );

        $this->app->when(IncomingMessageRelayService::class)
            ->needs(LoggerInterface::class)
            ->give(static fn (): LoggerInterface => Log::channel('max_log'));
    }
}
