<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthCheckController;

Route::post('/auth/check', [AuthCheckController::class, 'check']);
// Для NGINX-Gateway
Route::match(['get','post'],'/auth/verify', [\App\Http\Controllers\Api\AuthVerifyController::class, 'verify']);
