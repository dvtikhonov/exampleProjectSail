<?php

use App\Http\Controllers\Api\Food\AdminAiAccessController;
use App\Http\Controllers\Api\Food\AdminDishAvailabilityController;
use App\Http\Controllers\Api\Food\AdminDishController;
use App\Http\Controllers\Api\Food\AdminDraftAfterScanningOrderController;
use App\Http\Controllers\Api\Food\AdminManualOrderCartController;
use App\Http\Controllers\Api\Food\AdminManualOrderQueryController;
use App\Http\Controllers\Api\Food\AdminMaxBotTestController;
use App\Http\Controllers\Api\Food\AdminMenuCategoryController;
use App\Http\Controllers\Api\Food\AdminOrderCompositionController;
use App\Http\Controllers\Api\Food\AdminOrderReviewQueryController;
use App\Http\Controllers\Api\Food\AdminOrderReviewStepController;
use App\Http\Controllers\Api\Food\CartController;
use App\Http\Controllers\Api\Food\DishImageController;
use App\Http\Controllers\Api\Food\OrderChatController;
use App\Http\Controllers\Api\Food\OrderController;
use App\Http\Controllers\Api\Food\PhotoTextOrderController;
use App\Http\Controllers\Api\Food\PhotoTextScheduleController;
use App\Http\Controllers\Api\Food\RestaurantController;
use App\Http\Controllers\Api\MaxAuthController;
use App\Http\Controllers\Api\MaxWebhookController;
use App\Modules\FoodReport\Http\Controllers\AdminFoodReportExportController;
use App\Modules\FoodReport\Http\Controllers\AdminFoodReportQueryController;
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
            Route::get('/me', [AdminOrderReviewQueryController::class, 'me']);
            Route::get('/orders', [AdminOrderReviewQueryController::class, 'index']);
            Route::get('/orders/{order}', [AdminOrderReviewQueryController::class, 'show'])
                ->whereNumber('order');

            Route::middleware('food.order.admin:max_manager')->group(function () {
                Route::get('/ai-access', [AdminAiAccessController::class, 'show']);
                Route::post('/ai-access/toggle', [AdminAiAccessController::class, 'toggle']);
            });

            Route::post('/orders/{order}/address/approve', [AdminOrderReviewStepController::class, 'approveAddress'])
                ->middleware('food.order.admin:address_reviewer')
                ->whereNumber('order');
            Route::post('/orders/{order}/address/reject', [AdminOrderReviewStepController::class, 'rejectAddress'])
                ->middleware('food.order.admin:address_reviewer')
                ->whereNumber('order');
            Route::post('/orders/{order}/payment/approve', [AdminOrderReviewStepController::class, 'approvePayment'])
                ->middleware('food.order.admin:address_reviewer')
                ->whereNumber('order');
            Route::post('/orders/{order}/payment/reject', [AdminOrderReviewStepController::class, 'rejectPayment'])
                ->middleware('food.order.admin:address_reviewer')
                ->whereNumber('order');
            Route::post('/orders/{order}/composition/approve', [AdminOrderReviewStepController::class, 'approveComposition'])
                ->middleware('food.order.admin:composition_reviewer')
                ->whereNumber('order');
            Route::post('/orders/{order}/composition/reject', [AdminOrderReviewStepController::class, 'rejectComposition'])
                ->middleware('food.order.admin:composition_reviewer')
                ->whereNumber('order');
            Route::put('/orders/{order}/composition', [AdminOrderCompositionController::class, 'updateComposition'])
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

            Route::prefix('reports')
                ->middleware('food.order.admin:max_manager')
                ->group(function () {
                    Route::get('/revenue', [AdminFoodReportQueryController::class, 'revenue']);
                    Route::get('/top-dishes', [AdminFoodReportQueryController::class, 'topDishes']);
                    Route::post('/export', [AdminFoodReportExportController::class, 'export']);
                });

            Route::prefix('manual-orders')
                ->middleware('food.order.admin:max_manager')
                ->group(function () {
                    Route::get('/', [AdminManualOrderQueryController::class, 'index']);
                    Route::get('/users', [AdminManualOrderQueryController::class, 'users']);
                    Route::get('/cart', [AdminManualOrderCartController::class, 'showCart']);
                    Route::patch('/cart', [AdminManualOrderCartController::class, 'updateDeliveryAddress']);
                    Route::delete('/cart', [AdminManualOrderCartController::class, 'clearCart']);
                    Route::post('/cart/items', [AdminManualOrderCartController::class, 'storeItem']);
                    Route::patch('/cart/items/{item}', [AdminManualOrderCartController::class, 'updateItem'])
                        ->whereNumber('item');
                    Route::delete('/cart/items/{item}', [AdminManualOrderCartController::class, 'destroyItem'])
                        ->whereNumber('item');
                    Route::post('/submit', [AdminManualOrderCartController::class, 'submit']);
                    Route::post('/{order}/complete', [AdminDraftAfterScanningOrderController::class, 'complete'])
                        ->whereNumber('order');
                    Route::post('/{order}/move-to-cart', [AdminDraftAfterScanningOrderController::class, 'moveToCart'])
                        ->whereNumber('order');
                    Route::delete('/{order}', [AdminDraftAfterScanningOrderController::class, 'destroy'])
                        ->whereNumber('order');
                    Route::get('/{order}', [AdminManualOrderQueryController::class, 'show'])
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
