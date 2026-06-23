<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Стандартный JSON-ответ 401 для неаутентифицированного gateway-пользователя.
 */
final class GatewayUnauthorizedResponse
{
    public const MESSAGE = 'Unauthorized gateway user.';

    /**
     * Формирует JSON-ответ 401 Unauthorized.
     */
    public static function make(): JsonResponse
    {
        return response()->json(
            ['message' => self::MESSAGE],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
