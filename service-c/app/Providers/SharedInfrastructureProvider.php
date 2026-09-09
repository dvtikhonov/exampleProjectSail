<?php

namespace App\Providers;

use App\Contracts\Auth\GatewayAuthSessionInterface;
use App\Contracts\Auth\GatewayUserContextInterface;
use App\Contracts\Auth\GatewayUserResolverInterface;
use App\Contracts\Shared\ApplicationConfigInterface;
use App\Contracts\Shared\ApplicationEnvironmentInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\Contracts\Shared\ClockInterface;
use App\Contracts\Shared\CurrentHttpRequestInterface;
use App\Contracts\Shared\FileStorageInterface;
use App\Contracts\Shared\HttpClientInterface;
use App\Contracts\Shared\JobDispatcherInterface;
use App\Contracts\Shared\LocalFileWriterInterface;
use App\Contracts\Shared\RequestTimingRecorderInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\Infrastructure\Laravel\LaravelApplicationConfig;
use App\Infrastructure\Laravel\LaravelApplicationEnvironment;
use App\Infrastructure\Laravel\LaravelCacheStore;
use App\Infrastructure\Laravel\LaravelClock;
use App\Infrastructure\Laravel\LaravelCurrentHttpRequest;
use App\Infrastructure\Laravel\LaravelFileStorage;
use App\Infrastructure\Laravel\LaravelGatewayAuthSession;
use App\Infrastructure\Laravel\LaravelHttpClient;
use App\Infrastructure\Laravel\LaravelJobDispatcher;
use App\Infrastructure\Laravel\LaravelLocalFileWriter;
use App\Infrastructure\Laravel\LaravelRequestTimingRecorder;
use App\Infrastructure\Laravel\LaravelTransactionManager;
use App\Infrastructure\Laravel\RequestGatewayUserContext;
use App\Repositories\Auth\EloquentGatewayUserResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/**
 * DI-привязки общей инфраструктуры (clock, cache, HTTP, auth gateway и т.п.).
 */
class SharedInfrastructureProvider extends ServiceProvider
{
    /**
     * Регистрирует shared-контракты в контейнере.
     */
    public function register(): void
    {
        $this->app->bind(GatewayUserContextInterface::class, RequestGatewayUserContext::class);
        $this->app->bind(GatewayUserResolverInterface::class, EloquentGatewayUserResolver::class);
        $this->app->bind(GatewayAuthSessionInterface::class, LaravelGatewayAuthSession::class);
        $this->app->bind(TransactionManagerInterface::class, LaravelTransactionManager::class);
        $this->app->bind(ClockInterface::class, LaravelClock::class);
        $this->app->bind(JobDispatcherInterface::class, LaravelJobDispatcher::class);
        $this->app->bind(FileStorageInterface::class, LaravelFileStorage::class);
        $this->app->bind(ApplicationConfigInterface::class, LaravelApplicationConfig::class);
        $this->app->bind(ApplicationEnvironmentInterface::class, LaravelApplicationEnvironment::class);
        $this->app->bind(HttpClientInterface::class, LaravelHttpClient::class);
        $this->app->bind(CacheStoreInterface::class, LaravelCacheStore::class);
        $this->app->bind(LocalFileWriterInterface::class, LaravelLocalFileWriter::class);
        $this->app->bind(RequestTimingRecorderInterface::class, LaravelRequestTimingRecorder::class);
        $this->app->bind(CurrentHttpRequestInterface::class, LaravelCurrentHttpRequest::class);
        $this->app->bind(LoggerInterface::class, static fn (): LoggerInterface => Log::channel());
    }
}
