<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateMaxMiniApp;
use App\Http\Middleware\EnsureFoodOrderAdmin;
use App\Http\Middleware\TrustGatewayAuth;
use App\Http\Middleware\VerifyMaxWebhookSecret;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'trust.gateway' => TrustGatewayAuth::class,
            'max.webhook.secret' => VerifyMaxWebhookSecret::class,
            'max.miniapp.auth' => AuthenticateMaxMiniApp::class,
            'food.order.admin' => EnsureFoodOrderAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
