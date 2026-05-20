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

        return response()->json(['status' => 'ok', 'token' => $token], 200);

    }
}
