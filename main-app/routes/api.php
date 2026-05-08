<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthCheckController;
use Illuminate\Http\Request;

Route::post('/auth/check', [AuthCheckController::class, 'check']);
Route::middleware('auth:api')->get('/auth/verify', function (Request $request) {
    return response('', 200)
        ->header('X-User-Id', $request->user()->id);
});
