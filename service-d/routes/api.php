<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/organization', [OrganizationController::class, 'show']);
    Route::post('/organization/resolve', [OrganizationController::class, 'resolve']);
    Route::post('/organization/confirm', [OrganizationController::class, 'confirm']);
    Route::get('/organization/sync-status', [OrganizationController::class, 'syncStatus']);
    Route::post('/organization/resync', [OrganizationController::class, 'resync']);
    Route::get('/organization/reviews', [OrganizationController::class, 'reviews']);
});
