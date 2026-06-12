<?php

use App\Http\Controllers\Api\Food\CartController;
use App\Http\Controllers\Api\Food\OrderController;
use App\Http\Controllers\Api\Food\RestaurantController;
use App\Http\Controllers\Api\MaxAuthController;
use App\Http\Controllers\Api\MaxWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/max', MaxWebhookController::class)
    ->middleware('max.webhook.secret');

Route::post('/max/auth', [MaxAuthController::class, 'store']);

Route::middleware('max.miniapp.auth')->group(function () {
    if (app()->environment(['local', 'testing'])) {
        Route::get('/max/me', static fn (Request $request) => response()->json([
            'max_user_id' => $request->user()?->max_user_id,
        ]));
    }

    Route::prefix('food')->group(function () {
        Route::get('/restaurants', [RestaurantController::class, 'index']);
        Route::get('/restaurants/{restaurant}/menu', [RestaurantController::class, 'menu']);

        Route::get('/cart', [CartController::class, 'show']);
        Route::post('/cart/items', [CartController::class, 'store']);
        Route::patch('/cart/items/{item}', [CartController::class, 'update']);
        Route::delete('/cart/items/{item}', [CartController::class, 'destroy']);

        Route::post('/orders/submit', [OrderController::class, 'submit']);
    });
});

Route::middleware('trust.gateway')->group(function () {
    if (app()->environment(['local', 'testing'])) {
        Route::get('/data', static fn (Request $request) => response()->json([
            'user' => ['id' => $request->user()?->id],
        ]));
    }
});
