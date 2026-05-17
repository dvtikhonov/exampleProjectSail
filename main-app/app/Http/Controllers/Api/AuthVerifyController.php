<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Passport\Token;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;

class AuthVerifyController extends Controller
{
    public function verify(Request $request): JsonResponse|Response
    {
        $bearerToken = $request->bearerToken();
        if (! $bearerToken) {
            return $this->inactiveResponse();
        }

        try {
            $encoder = new JoseEncoder;
            $parser = new Parser($encoder);
            $jwt = $parser->parse($bearerToken);
            $claims = $jwt->claims();

            if (! $claims->has('jti')) {
                return $this->inactiveResponse();
            }
            $tokenId = $claims->get('jti');

            // 4. Ищем токен в БД через модель Token
            $token = Token::find($tokenId);
            // 5. Проверяем, существует ли токен, не отозван ли и не истек ли срок его действия
            if (! $token || $token->revoked) {
                return $this->inactiveResponse();
            }
            if ($token->expires_at && Carbon::parse($token->expires_at)->isPast()) {
                return $this->inactiveResponse();
            }

            return response(['user_id' => $token->user_id], 200)->header('X-User-Id', $token->user_id);
        } catch (\Throwable $e) {
            return $this->inactiveResponse();
        }
    }

    private function inactiveResponse(): JsonResponse
    {
        return response()->json(['active' => false], 401);
    }
}
