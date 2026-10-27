<?php

declare(strict_types=1);

use App\Exceptions\Food\FoodDomainException;
use App\Http\Middleware\AuthenticateMaxMiniApp;
use App\Http\Middleware\EnsureFoodOrderAdmin;
use App\Http\Middleware\EnsurePhotoTextAiAccess;
use App\Http\Middleware\ForbidProduction;
use App\Http\Middleware\ForbidTelegramMiniApp;
use App\Http\Middleware\TrustGatewayAuth;
use App\Http\Middleware\VerifyBrisklySyncCaptureSecret;
use App\Http\Middleware\VerifyMaxWebhookSecret;
use App\Http\Middleware\VerifyMessengerChoiceToken;
use App\Http\Middleware\VerifyPhotoTextAgentToken;
use App\Http\Middleware\VerifyPhotoTextWriteToken;
use App\Http\Middleware\VerifyTelegramWebhookSecret;
use App\Modules\MaxIncomingRelay\Exceptions\BotDmDomainException;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;
use App\Modules\SchedulePhoto\Interface\Http\Middleware\VerifySchedulePhotoCaptureSecret;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            require __DIR__.'/../routes/internal.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trusted proxies: config/trustedproxy.php (TRUSTED_PROXIES or private CIDRs).
        // Direct :8083 without a proxy — trust not needed; via nginx-gateway — private defaults apply.
        // Do not call trustProxies(at: ...) here: env/config are unavailable in this callback;
        // TrustProxies reads config('trustedproxy.proxies') at request time.

        $middleware->throttleApi('api');

        $middleware->alias([
            'trust.gateway' => TrustGatewayAuth::class,
            'max.webhook.secret' => VerifyMaxWebhookSecret::class,
            'telegram.webhook.secret' => VerifyTelegramWebhookSecret::class,
            'briskly.capture.secret' => VerifyBrisklySyncCaptureSecret::class,
            'messenger.choice.token' => VerifyMessengerChoiceToken::class,
            'schedule-photo.capture.secret' => VerifySchedulePhotoCaptureSecret::class,
            'max.miniapp.auth' => AuthenticateMaxMiniApp::class,
            'forbid.telegram.miniapp' => ForbidTelegramMiniApp::class,
            'food.order.admin' => EnsureFoodOrderAdmin::class,
            'phototext.agent.token' => VerifyPhotoTextAgentToken::class,
            'phototext.write.token' => VerifyPhotoTextWriteToken::class,
            'phototext.ai.access' => EnsurePhotoTextAiAccess::class,
            'forbid.production' => ForbidProduction::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('internal/*') || $request->expectsJson(),
        );

        $exceptions->render(function (FoodDomainException $exception, Request $request): JsonResponse {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        });

        $exceptions->render(function (BotDmDomainException $exception, Request $request): JsonResponse {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        });

        $exceptions->render(function (SchedulePhotoDomainException $exception, Request $request): JsonResponse {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->statusCode());
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('food:sync-dish-availability')
            ->dailyAt('03:00')
            ->timezone('Europe/Moscow');
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('max:webhook:subscribe')
            ->dailyAt('00:10')
            ->timezone('Europe/Moscow');
    })
    ->create();
