<?php

use App\Http\Controllers\Api\SalesOutletsExportController;
use Illuminate\Support\Facades\Route;

Route::middleware('trust.gateway')->group(function () {
    // ваши защищённые маршруты
    Route::get('/data', function () {
        return response()->json(['user' => auth()->user()]);
    });
    Route::post('/sales-outlets/exports', [SalesOutletsExportController::class, 'store']);
    Route::get('/sales-outlets/exports/{uuid}', [SalesOutletsExportController::class, 'show']);
    Route::get('/sales-outlets/exports/{uuid}/download', [SalesOutletsExportController::class, 'download']);
});
