<?php

use App\Contracts\Max\MaxMiniAppAccessLoggerInterface;
use App\Http\Mappers\MaxMiniAppAccessContextMapper;
use App\Http\Support\MaxAppRequestContext;
use App\Http\Support\MaxLocalDevInitData;
use App\Http\Support\TelegramLocalDevInitData;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/max-app', function (
    MaxMiniAppAccessLoggerInterface $accessLogger,
    MaxMiniAppAccessContextMapper $accessContextMapper,
) {
    $accessLogger->logPageRequest($accessContextMapper->fromPageRequest(request()));

    if (! MaxAppRequestContext::isLocalDevelopmentRequest()) {
        // MAX (ПК/мобильный) через туннель: только production build, не Vite dev (localhost:5174).
        Vite::useHotFile(storage_path('framework/vite-max-app-no-hot'));
    }

    return response()
        ->view('max-app', [
            'localDevInitData' => MaxLocalDevInitData::build(request()),
        ])
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
        ->header('Pragma', 'no-cache');
});

Route::get('/tg-app', function () {
    if (! MaxAppRequestContext::isLocalDevelopmentRequest()) {
        // Telegram через публичный URL: только production build, не Vite dev (localhost:5174).
        Vite::useHotFile(storage_path('framework/vite-max-app-no-hot'));
    }

    return response()
        ->view('tg-app', [
            'localDevInitData' => TelegramLocalDevInitData::build(request()),
        ])
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
        ->header('Pragma', 'no-cache');
});
