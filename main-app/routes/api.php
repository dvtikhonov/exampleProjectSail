<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthVerifyController;
use App\Http\Controllers\AuthCheckController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/check', [AuthCheckController::class, 'check']);
// Для NGINX-Gateway
Route::match(['get', 'post'], '/auth/verify', [AuthVerifyController::class, 'verify']);
