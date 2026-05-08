<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthCheckController extends Controller
{
    public function check(Request $request)
    {
        // middleware 'auth:api' уже проверил токен через Passport
        // Если дошли сюда – токен валиден
        return response()->json(['valid' => true], 200);
    }

}
