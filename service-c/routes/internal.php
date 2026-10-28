<?php

declare(strict_types=1);

use App\Http\Controllers\InternalMessengerChoiceController;
use Illuminate\Support\Facades\Route;

/*
| Внутренние вызовы из Docker-сети, без префикса /api и без auth_request gateway.
| CSRF нет: маршрут вне группы web. Throttle api нет: все визиты идут с одного IP service-j.
*/

Route::post('/internal/messenger-choice', [InternalMessengerChoiceController::class, 'store'])
    ->middleware('messenger.choice.token');

Route::post('/internal/messenger-choice/clicks', [InternalMessengerChoiceController::class, 'storeClick'])
    ->middleware('messenger.choice.token');
