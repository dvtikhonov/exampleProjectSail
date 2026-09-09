<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Max\MaxMiniAppAccessLoggerInterface;
use App\Contracts\Max\MaxMiniAppAuthServiceInterface;
use App\Contracts\Max\MaxWebAppInitDataValidatorInterface;
use App\Exceptions\Max\MaxWebAppInitDataException;
use App\Http\Controllers\Controller;
use App\Http\Mappers\MaxMiniAppAccessContextMapper;
use App\Http\Requests\Max\ValidateInitDataRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Аутентификация MAX mini-app по initData и выдача Bearer-токена.
 */
class MaxAuthController extends Controller
{
    public function __construct(
        private readonly MaxWebAppInitDataValidatorInterface $initDataValidator,
        private readonly MaxMiniAppAuthServiceInterface $authService,
        private readonly MaxMiniAppAccessLoggerInterface $accessLogger,
        private readonly MaxMiniAppAccessContextMapper $accessContextMapper,
    ) {}

    /**
     * Проверяет initData и возвращает access token Sanctum.
     */
    public function store(ValidateInitDataRequest $request): JsonResponse
    {
        try {
            $initData = $this->initDataValidator->validate($request->initData());
        } catch (MaxWebAppInitDataException $exception) {
            $this->accessLogger->logAuthRequest(
                $this->accessContextMapper->fromAuthRequest($request),
                Response::HTTP_UNAUTHORIZED,
            );

            return response()->json([
                'message' => 'Invalid MAX initData.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->authService->issueToken($initData);
        $this->accessLogger->logAuthRequest(
            $this->accessContextMapper->fromAuthRequest($request),
            Response::HTTP_OK,
            $initData->maxUserId,
        );

        return response()->json($payload);
    }
}
