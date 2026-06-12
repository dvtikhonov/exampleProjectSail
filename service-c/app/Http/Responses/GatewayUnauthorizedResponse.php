<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class GatewayUnauthorizedResponse
{
    public const MESSAGE = 'Unauthorized gateway user.';

    public static function make(): JsonResponse
    {
        return response()->json(
            ['message' => self::MESSAGE],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
