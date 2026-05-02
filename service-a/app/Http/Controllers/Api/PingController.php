<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
}
