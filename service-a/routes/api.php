<?php

use App\Http\Controllers\Api\PingController;
use Illuminate\Support\Facades\Route;

Route::post('/ping', [PingController::class, 'ping']);
