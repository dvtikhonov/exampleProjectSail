<?php

use App\Http\Controllers\Api\PingController;
use App\Http\Controllers\Api\SalesOutletsController;
use Illuminate\Support\Facades\Route;


Route::get('/pingS', [PingController::class, 'pingS']);
Route::post('/ping', [PingController::class, 'ping']);
Route::middleware('trust.gateway')->group(function (): void {
    Route::get('/sales-outlets', [SalesOutletsController::class, 'index']);
    Route::patch('/sales-outlets/{salesOutlet}', [SalesOutletsController::class, 'update']);
    Route::post('/sales-outlets/{salesOutlet}/head-organization', [SalesOutletsController::class, 'updateHeadOrganization']);
    Route::delete('/sales-outlets/{salesOutlet}', [SalesOutletsController::class, 'destroy']);
});
//Route::middleware('auth:api')->get('/pingS', [PingController::class, 'pingS']);

//Route::get('/pingS', function (\Illuminate\Support\Facades\Request $request) {
//
//    return response()->json(['status' => 'ok', 'user_id' => 'ZZZZZ777']);
//
//
//    $token = $request->bearerToken();
//    if (!$token) {
//        return response()->json(['error' => 'No token'], 401);
//    }
//    // Попробуйте найти валидный токен в БД Passport
//    $tokenRecord = \Illuminate\Support\Facades\DB::table('oauth_access_tokens')
//        ->where('id', hash('sha256', explode(' ', $token)[1] ?? '')) // упрощённо, лучше через проверку Passport
//        ->first();
//    if ($tokenRecord && $tokenRecord->revoked == 0) {
//        return response()->json(['status' => 'ok', 'user_id' => $tokenRecord->user_id]);
//    }
//    return response()->json(['error' => 'Invalid token'], 401);
//});
