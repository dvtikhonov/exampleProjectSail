<?php

declare(strict_types=1);

use App\Modules\SchedulePhoto\Interface\Http\Controllers\AdminSchedulePhotoController;
use App\Modules\SchedulePhoto\Interface\Http\Controllers\InternalSchedulePhotoOcrController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SchedulePhoto module routes (prefix /api из RouteServiceProvider / bootstrap)
|--------------------------------------------------------------------------
*/

Route::post('/food/internal/schedule-photo/ocr-complete', [InternalSchedulePhotoOcrController::class, 'complete'])
    ->middleware(['throttle:60,1,schedule-photo-ocr-complete', 'schedule-photo.capture.secret']);

Route::middleware(['max.miniapp.auth', 'forbid.telegram.miniapp'])
    ->prefix('food/admin/schedule-photo')
    ->group(function (): void {
    Route::middleware('food.order.admin:menu_manager')->group(function (): void {
        Route::get('/config', [AdminSchedulePhotoController::class, 'config'])
            ->middleware('throttle:60,1,schedule-photo-config');
        Route::get('/restaurants', [AdminSchedulePhotoController::class, 'restaurants'])
            ->middleware('throttle:60,1,schedule-photo-restaurants');
        Route::get('/catalog', [AdminSchedulePhotoController::class, 'catalog'])
            ->middleware('throttle:60,1,schedule-photo-catalog');
        Route::post('/readiness', [AdminSchedulePhotoController::class, 'readiness'])
            ->middleware('throttle:30,1,schedule-photo-readiness');
        Route::post('/runs', [AdminSchedulePhotoController::class, 'store'])
            ->middleware('throttle:10,1,schedule-photo-runs');
        Route::get('/runs/{runId}', [AdminSchedulePhotoController::class, 'show'])
            ->middleware('throttle:60,1,schedule-photo-show')
            ->whereUuid('runId');
        Route::post('/runs/{runId}/apply', [AdminSchedulePhotoController::class, 'apply'])
            ->middleware('throttle:10,1,schedule-photo-apply')
            ->whereUuid('runId');
        Route::post('/runs/{runId}/resolve-discarded', [AdminSchedulePhotoController::class, 'resolveDiscarded'])
            ->middleware('throttle:30,1,schedule-photo-resolve')
            ->whereUuid('runId');
    });
});
