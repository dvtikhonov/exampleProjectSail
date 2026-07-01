<?php

declare(strict_types=1);

namespace App\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/** JSON-ответ 401 при отсутствии или невалидном заголовке X-User-Id. */
final class GatewayUnauthorizedResponse
{
    public const string MESSAGE = 'Unauthorized gateway user.';

    /** Возвращает стандартный JSON-ответ 401 для неавторизованного gateway-запроса. */
    public static function make(): JsonResponse
    {
        return new JsonResponse(
            ['message' => self::MESSAGE],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
