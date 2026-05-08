<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class PingController extends Controller
{
    public function ping(Request $request)
    {
        return response()->json([
            'status'  => 'ok',
            'message' => 'Connection confirmed_777',
            'data'    => $request->all(),
        ]);

    }
    public function pingS(Request $request)
    {
        $token = $request->bearerToken();
        $lll = hash('sha256', explode(' ', $token)[1] ?? '');
        Log::info('pingS. id='. $lll, $request->all());



    $token = $request->bearerToken();
    if (!$token) {
        return response()->json(['error' => 'No token'], 401);
    }
    // Попробуйте найти валидный токен в БД Passport
    $tokenRecord = \Illuminate\Support\Facades\DB::table('oauth_access_tokens')
        ->where('id', hash('sha256', explode(' ', $token)[1] ?? '')) // упрощённо, лучше через проверку Passport
        ->first();
//    if ($tokenRecord && $tokenRecord->revoked == 0) {
//        return response()->json(['status' => 'ok', 'user_id' => $tokenRecord->user_id]);
//    }

    return response()->json(['error' => 'Invalid token'], 401);

        $user = \Illuminate\Support\Facades\DB::table('users')
            ->where('id',1)
            ->first();


//        Log::info('pingS. id='. $lll, $request->headers->all());
        Log::info('pingS-777. id='. $lll, $tokenRecord->user_id ?? ['result' => 'NOT Data', 'auth' => $user->name]);
        return response()->json(['status' => 'ok', 'user_id' => 'ZZZZZ777']);
//        return response()->json([
//            'status'  => 'ok',
//            'message' => 'Connection confirmed_777SSS',
//            'data'    => $request->all(),
//        ]);

    }
}
