<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Laravel\Passport\Token;

class AuthVerifyController extends Controller
{
    public function verify(Request $request)
    {
        $bearerToken = $request->bearerToken() ;
        if (!$bearerToken) {
            return response()->json(['active' => false], 401);
        }

        try {
            $encoder = new JoseEncoder();
            $parser = new Parser($encoder);
            $jwt = $parser->parse($bearerToken);
            $claims = $jwt->claims();

            if (!$claims->has('jti')) {
                return response()->json(['active' => false], 401);
            }
            $tokenId = $claims->get('jti');

            // 4. Ищем токен в БД через модель Token
            $token = Token::find($tokenId);
            // 5. Проверяем, существует ли токен, не отозван ли и не истек ли срок его действия
            if (!$token || $token->revoked) {
                return response()->json(['active' => false], 401);
            }
            if ($token->expires_at && $token->expires_at->isPast()) {
                return response()->json(['active' => false], 401);
            }

            return response(['user_id' => $token->user_id], 200)->header('X-User-Id', $token->user_id);
        } catch (\Throwable $e) {
            return response()->json(['active' => false], 401);
        }
    }
}
