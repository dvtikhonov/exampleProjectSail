<?php

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return view('welcome');
});
// Route::get('/pingS', [\App\Http\Controllers\Api\PingController::class, 'pingS']);
