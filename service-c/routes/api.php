<?php

use App\Http\Controllers\Api\Food\AdminOrderReviewController;
use App\Http\Controllers\Api\Food\CartController;
use App\Http\Controllers\Api\Food\DishImageController;
use App\Http\Controllers\Api\Food\OrderController;
use App\Http\Controllers\Api\Food\RestaurantController;
use App\Http\Controllers\Api\MaxAuthController;
use App\Http\Controllers\Api\MaxWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/max', MaxWebhookController::class)
    ->middleware('max.webhook.secret');

Route::post('/max/auth', [MaxAuthController::class, 'store']);

// Публичный same-origin URL для <img> (без Bearer — WebView MAX не шлёт Authorization на картинки).
Route::get('/food/dishes/{dish}/image', [DishImageController::class, 'show']);

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
        Route::patch('/cart', [CartController::class, 'updateDeliveryAddress']);
        Route::delete('/cart', [CartController::class, 'clear']);
        Route::post('/cart/items', [CartController::class, 'store']);
        Route::patch('/cart/items/{item}', [CartController::class, 'update']);
        Route::delete('/cart/items/{item}', [CartController::class, 'destroy']);

        Route::post('/orders/submit', [OrderController::class, 'submit']);

        Route::prefix('admin')->group(function () {
            Route::get('/me', [AdminOrderReviewController::class, 'me']);
            Route::get('/orders', [AdminOrderReviewController::class, 'index']);
            Route::get('/orders/{order}', [AdminOrderReviewController::class, 'show'])
                ->whereNumber('order');

            Route::post('/orders/{order}/address/approve', [AdminOrderReviewController::class, 'approveAddress'])
                ->middleware('food.order.admin:address_reviewer')
                ->whereNumber('order');
            Route::post('/orders/{order}/address/reject', [AdminOrderReviewController::class, 'rejectAddress'])
                ->middleware('food.order.admin:address_reviewer')
                ->whereNumber('order');
            Route::post('/orders/{order}/composition/approve', [AdminOrderReviewController::class, 'approveComposition'])
                ->middleware('food.order.admin:composition_reviewer')
                ->whereNumber('order');
            Route::post('/orders/{order}/composition/reject', [AdminOrderReviewController::class, 'rejectComposition'])
                ->middleware('food.order.admin:composition_reviewer')
                ->whereNumber('order');
        });
    });
});

Route::middleware('trust.gateway')->group(function () {
    if (app()->environment(['local', 'testing'])) {
        Route::get('/data', static fn (Request $request) => response()->json([
            'user' => ['id' => $request->user()?->id],
        ]));
    }
});
