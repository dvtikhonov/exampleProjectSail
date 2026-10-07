<?php

namespace App\Providers;

use App\Http\Support\MaxAppRequestContext;
use App\Infrastructure\Laravel\FailBrisklySyncMatchOnQueueJobFailed;
use App\Infrastructure\Laravel\SafeCallQueuedHandler;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\CallQueuedHandler;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobPopped;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

/**
 * Тонкий composition root: URL/FORCE_ROOT_URL + named rate limiters `api`, `food-dish-image`.
 * DI-привязки — в SharedInfrastructureProvider, FoodServiceProvider, MaxServiceProvider.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Регистрирует безопасный CallQueuedHandler (preload + incomplete debounce).
     */
    public function register(): void
    {
        $this->app->bind(CallQueuedHandler::class, SafeCallQueuedHandler::class);
    }

    /**
     * Настраивает схему и корневой URL для HTTPS-туннеля и прокси.
     */
    public function boot(): void
    {
        Event::listen(JobPopped::class, static function (JobPopped $event): void {
            if ($event->job === null) {
                return;
            }
            $commandName = $event->job->payload()['data']['commandName'] ?? null;
            if (! is_string($commandName) || $commandName === '' || ! str_starts_with($commandName, 'App\\')) {
                return;
            }
            // Только Composer autoload — сырой require_once ломается без trait-зависимостей.
            class_exists($commandName);
        });
        Event::listen(JobProcessing::class, static function (JobProcessing $event): void {
            $commandName = $event->job->payload()['data']['commandName'] ?? null;
            if (is_string($commandName) && $commandName !== '') {
                class_exists($commandName);
            }
        });
        Event::listen(JobFailed::class, FailBrisklySyncMatchOnQueueJobFailed::class);

        RateLimiter::for('api', static function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        // Публичный dish image: ужесточение против перебора id (без Bearer в <img>).
        RateLimiter::for('food-dish-image', static function (Request $request): Limit {
            return Limit::perMinute(30)->by($request->ip());
        });

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
