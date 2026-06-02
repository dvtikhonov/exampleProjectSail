<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\PassportTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthVerifyController extends Controller
{
    public function __construct(
        private readonly PassportTokenVerifier $tokenVerifier,
    ) {}

    public function verify(Request $request): JsonResponse|Response
    {
        $userId = $this->tokenVerifier->resolveUserId($request->bearerToken());

        if ($userId === null) {
            return $this->inactiveResponse();
        }

        return response(['user_id' => $userId], 200)->header('X-User-Id', (string) $userId);
    }

    private function inactiveResponse(): JsonResponse
    {
        return response()->json(['active' => false], 401);
    }
}
