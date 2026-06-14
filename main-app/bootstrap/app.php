<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateBroadcastingPassport;
use App\Http\Middleware\HandleAuthPassport;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web', AuthenticateBroadcastingPassport::class]],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'api/auth/check',   // исключаем эндпоинт проверки токена
            'api/auth/verify',   // исключаем эндпоинт проверки токена
        ]);
        $middleware->alias([
            'auth.passport' => HandleAuthPassport::class,
            'auth.broadcasting.passport' => AuthenticateBroadcastingPassport::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
