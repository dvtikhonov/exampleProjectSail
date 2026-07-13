<?php

use Illuminate\Support\Facades\Route;

/** JSON-информация о сервисе и ссылках на auth API и фронтенд. */
Route::get('/', function () {
    $frontendUrl = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');

    return response()->json([
        'service' => 'service-g',
        'frontend' => $frontendUrl,
        'api' => '/api',
        'login' => '/api/auth/login',
        'register' => '/api/auth/register',
        'user' => '/api/user',
        'tasks' => '/api/tasks',
        'openapi' => '/api/docs/openapi.yaml',
        'login_page' => $frontendUrl.'/login',
        'register_page' => $frontendUrl.'/register',
        'test_login_page' => url('/login'),
        'test_register_page' => url('/register'),
    ]);
});

/** Тестовые страницы auth для прямого доступа к Laravel API (localhost:8088). */
Route::view('/login', 'test-login', ['registerMode' => false]);
Route::view('/register', 'test-login', ['registerMode' => true]);
