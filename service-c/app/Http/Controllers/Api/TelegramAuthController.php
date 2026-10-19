<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Telegram\TelegramMiniAppAuthServiceInterface;
use App\Contracts\Telegram\TelegramWebAppInitDataValidatorInterface;
use App\Exceptions\Telegram\TelegramWebAppInitDataException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Telegram\ValidateTelegramInitDataRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Аутентификация Telegram Mini App по initData и выдача Bearer-токена.
 */
class TelegramAuthController extends Controller
{
    public function __construct(
        private readonly TelegramWebAppInitDataValidatorInterface $initDataValidator,
        private readonly TelegramMiniAppAuthServiceInterface $authService,
    ) {}

    /**
     * Проверяет initData и возвращает access token Sanctum (ability telegram-miniapp).
     */
    public function store(ValidateTelegramInitDataRequest $request): JsonResponse
    {
        try {
            $initData = $this->initDataValidator->validate($request->initData());
        } catch (TelegramWebAppInitDataException) {
            return response()->json([
                'message' => 'Invalid Telegram initData.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json($this->authService->issueToken($initData));
    }
}
