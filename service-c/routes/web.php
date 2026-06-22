<?php

use App\Support\MaxAppRequestContext;
use App\Support\MaxLocalDevInitData;
use App\Support\MaxMiniAppAccessLogger;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/max-app', function (MaxMiniAppAccessLogger $accessLogger) {
    $accessLogger->logPageRequest(request());

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
