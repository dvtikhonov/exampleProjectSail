<?php

use App\Http\Controllers\Api\SalesOutletsController;
use Illuminate\Support\Facades\Route;

Route::middleware('trust.gateway')->group(function (): void {
    Route::get('/sales-outlets', [SalesOutletsController::class, 'index']);
    Route::patch('/sales-outlets/{salesOutlet}', [SalesOutletsController::class, 'update']);
    Route::post('/sales-outlets/{salesOutlet}/head-organization', [SalesOutletsController::class, 'updateHeadOrganization']);
    Route::delete('/sales-outlets/{salesOutlet}', [SalesOutletsController::class, 'destroy']);
});
