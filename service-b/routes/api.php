<?php

use App\Http\Controllers\Api\SalesOutletsReportController;
use App\Http\Controllers\Api\SalesOutletsReportStatsController;
use Illuminate\Support\Facades\Route;

Route::middleware('trust.gateway')->group(function () {
    // ваши защищённые маршруты
    Route::get('/data', function () {
        return response()->json(['user' => auth()->user()]);
    });
    Route::get('/sales-outlets/reports/stats', [SalesOutletsReportStatsController::class, 'index']);
    Route::post('/sales-outlets/reports', [SalesOutletsReportController::class, 'store']);
    Route::get('/sales-outlets/reports/{uuid}', [SalesOutletsReportController::class, 'show']);
    Route::get('/sales-outlets/reports/{uuid}/download', [SalesOutletsReportController::class, 'download']);
});
