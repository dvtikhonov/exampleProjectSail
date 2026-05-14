<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthCheckController;
use Illuminate\Http\Request;

Route::middleware('trust.gateway')->group(function () {
    // ваши защищённые маршруты
    Route::get('/data', function () {
        return response()->json(['user' => auth()->user()]);
    });
});
