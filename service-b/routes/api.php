<?php

use App\Http\Controllers\Api\SalesOutletsReportController;
use App\Http\Controllers\Api\SalesOutletsReportStatsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('trust.gateway')->group(function () {
    if (app()->environment(['local', 'testing'])) {
        Route::get('/data', static fn (Request $request) => response()->json([
            'user' => ['id' => $request->user()?->id],
        ]));
    }
    Route::get('/sales-outlets/reports/stats', [SalesOutletsReportStatsController::class, 'index']);
    Route::post('/sales-outlets/reports', [SalesOutletsReportController::class, 'store']);
    Route::get('/sales-outlets/reports/{uuid}', [SalesOutletsReportController::class, 'show']);
    Route::get('/sales-outlets/reports/{uuid}/download', [SalesOutletsReportController::class, 'download']);
});
