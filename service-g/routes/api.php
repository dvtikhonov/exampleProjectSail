<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
});

Route::middleware('auth:sanctum')->get('/user', [AuthController::class, 'user']);

Route::get('/docs/openapi.yaml', static function () {
    $path = base_path('docs/openapi.yaml');

    if (! is_file($path)) {
        abort(404);
    }

    return response(file_get_contents($path), 200, [
        'Content-Type' => 'application/yaml',
    ]);
});

Route::middleware('auth:sanctum')->apiResource('tasks', TaskController::class);
