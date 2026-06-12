<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Max\MaxWebAppInitDataValidatorInterface;
use App\Exceptions\Max\MaxWebAppInitDataException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Max\ValidateInitDataRequest;
use App\Services\Max\MaxMiniAppAuthService;
use App\Support\MaxMiniAppAccessLogger;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MaxAuthController extends Controller
{
    public function __construct(
        private readonly MaxWebAppInitDataValidatorInterface $initDataValidator,
        private readonly MaxMiniAppAuthService $authService,
        private readonly MaxMiniAppAccessLogger $accessLogger,
    ) {}

    public function store(ValidateInitDataRequest $request): JsonResponse
    {
        try {
            $initData = $this->initDataValidator->validate($request->initData());
        } catch (MaxWebAppInitDataException $exception) {
            $this->accessLogger->logAuthRequest($request, Response::HTTP_UNAUTHORIZED);

            return response()->json([
                'message' => 'Invalid MAX initData.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->authService->issueToken($initData);
        $this->accessLogger->logAuthRequest($request, Response::HTTP_OK, $initData->maxUserId);

        return response()->json($payload);
    }
}
