<?php

use App\Http\Controllers\Api\Food\AdminAiAccessController;
use App\Http\Controllers\Api\Food\AdminDishAvailabilityController;
use App\Http\Controllers\Api\Food\AdminDishController;
use App\Http\Controllers\Api\Food\AdminManualOrderController;
use App\Http\Controllers\Api\Food\AdminMaxBotTestController;
use App\Http\Controllers\Api\Food\AdminMenuCategoryController;
use App\Http\Controllers\Api\Food\AdminOrderReviewController;
use App\Http\Controllers\Api\Food\CartController;
use App\Http\Controllers\Api\Food\DishImageController;
use App\Http\Controllers\Api\Food\OrderChatController;
use App\Http\Controllers\Api\Food\OrderController;
use App\Http\Controllers\Api\Food\PhotoTextOrderController;
use App\Http\Controllers\Api\Food\PhotoTextScheduleController;
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

// Агент Cursor: токен X-PhotoText-Token + активный AI-доступ max_manager (ai_access_until > now).
Route::middleware(['phototext.agent.token', 'phototext.ai.access'])->prefix('food/phototext')->group(function () {
    Route::get('/restaurants', [PhotoTextOrderController::class, 'restaurants']);
    Route::get('/catalog', [PhotoTextOrderController::class, 'catalog']);
    Route::post('/match', [PhotoTextOrderController::class, 'match']);
    Route::post('/orders', [PhotoTextOrderController::class, 'store']);
    Route::post('/schedule/match', [PhotoTextScheduleController::class, 'match']);
    Route::post('/schedule/apply', [PhotoTextScheduleController::class, 'apply']);
});

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
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{order}', [OrderController::class, 'show'])
            ->whereNumber('order');
        Route::get('/orders/{order}/messages', [OrderChatController::class, 'index'])
            ->whereNumber('order');
        Route::post('/orders/{order}/messages', [OrderChatController::class, 'store'])
            ->whereNumber('order');

        Route::prefix('admin')->group(function () {
            Route::get('/me', [AdminOrderReviewController::class, 'me']);
            Route::get('/orders', [AdminOrderReviewController::class, 'index']);
            Route::get('/orders/{order}', [AdminOrderReviewController::class, 'show'])
                ->whereNumber('order');

            Route::middleware('food.order.admin:max_manager')->group(function () {
                Route::get('/ai-access', [AdminAiAccessController::class, 'show']);
                Route::post('/ai-access/toggle', [AdminAiAccessController::class, 'toggle']);
            });

            Route::post('/orders/{order}/address/approve', [AdminOrderReviewController::class, 'approveAddress'])
                ->middleware('food.order.admin:address_reviewer')
                ->whereNumber('order');
            Route::post('/orders/{order}/address/reject', [AdminOrderReviewController::class, 'rejectAddress'])
                ->middleware('food.order.admin:address_reviewer')
                ->whereNumber('order');
            Route::post('/orders/{order}/payment/approve', [AdminOrderReviewController::class, 'approvePayment'])
                ->middleware('food.order.admin:address_reviewer')
                ->whereNumber('order');
            Route::post('/orders/{order}/payment/reject', [AdminOrderReviewController::class, 'rejectPayment'])
                ->middleware('food.order.admin:address_reviewer')
                ->whereNumber('order');
            Route::post('/orders/{order}/composition/approve', [AdminOrderReviewController::class, 'approveComposition'])
                ->middleware('food.order.admin:composition_reviewer')
                ->whereNumber('order');
            Route::post('/orders/{order}/composition/reject', [AdminOrderReviewController::class, 'rejectComposition'])
                ->middleware('food.order.admin:composition_reviewer')
                ->whereNumber('order');
            Route::put('/orders/{order}/composition', [AdminOrderReviewController::class, 'updateComposition'])
                ->middleware('food.order.admin:composition_reviewer')
                ->whereNumber('order');

            Route::middleware('food.order.admin:menu_manager')->group(function () {
                Route::get('/menu-categories', [AdminMenuCategoryController::class, 'index']);
                Route::get('/menu-categories/{menuCategory}', [AdminMenuCategoryController::class, 'show'])
                    ->whereNumber('menuCategory');
                Route::post('/menu-categories', [AdminMenuCategoryController::class, 'store']);
                Route::put('/menu-categories/{menuCategory}', [AdminMenuCategoryController::class, 'update'])
                    ->whereNumber('menuCategory');
                Route::delete('/menu-categories/{menuCategory}', [AdminMenuCategoryController::class, 'destroy'])
                    ->whereNumber('menuCategory');

                Route::get('/dishes', [AdminDishController::class, 'index']);
                Route::post('/dishes/test-bot', [AdminMaxBotTestController::class, 'sendTestBot']);
                Route::post('/dishes/test-bot-2', [AdminMaxBotTestController::class, 'sendTestBot2']);
                Route::get('/dishes/{dish}', [AdminDishController::class, 'show'])
                    ->whereNumber('dish');
                Route::post('/dishes/import', [AdminDishController::class, 'import']);
                Route::post('/dishes', [AdminDishController::class, 'store']);
                Route::post('/dishes/{dish}', [AdminDishController::class, 'update'])
                    ->whereNumber('dish');
                Route::delete('/dishes/{dish}', [AdminDishController::class, 'destroy'])
                    ->whereNumber('dish');

                Route::get('/dish-availability-schedule', [AdminDishAvailabilityController::class, 'show']);
                Route::put('/dish-availability-schedule', [AdminDishAvailabilityController::class, 'sync']);
            });

            Route::prefix('manual-orders')
                ->middleware('food.order.admin:max_manager')
                ->group(function () {
                    Route::get('/', [AdminManualOrderController::class, 'index']);
                    Route::get('/users', [AdminManualOrderController::class, 'users']);
                    Route::get('/cart', [AdminManualOrderController::class, 'showCart']);
                    Route::patch('/cart', [AdminManualOrderController::class, 'updateDeliveryAddress']);
                    Route::delete('/cart', [AdminManualOrderController::class, 'clearCart']);
                    Route::post('/cart/items', [AdminManualOrderController::class, 'storeItem']);
                    Route::patch('/cart/items/{item}', [AdminManualOrderController::class, 'updateItem'])
                        ->whereNumber('item');
                    Route::delete('/cart/items/{item}', [AdminManualOrderController::class, 'destroyItem'])
                        ->whereNumber('item');
                    Route::post('/submit', [AdminManualOrderController::class, 'submit']);
                    Route::post('/{order}/complete', [AdminManualOrderController::class, 'complete'])
                        ->whereNumber('order');
                    Route::post('/{order}/move-to-cart', [AdminManualOrderController::class, 'moveToCart'])
                        ->whereNumber('order');
                    Route::delete('/{order}', [AdminManualOrderController::class, 'destroy'])
                        ->whereNumber('order');
                    Route::get('/{order}', [AdminManualOrderController::class, 'show'])
                        ->whereNumber('order');
                });
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
