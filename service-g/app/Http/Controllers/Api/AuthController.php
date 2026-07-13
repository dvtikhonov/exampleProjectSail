<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API аутентификации для Nuxt SPA (Sanctum: cookie + session).
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->toDto());

        return response()->json([
            'user' => $user->toArray(),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->login($request->toDto(), $request->ip());

        return response()->json([
            'user' => $user->toArray(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => $this->authService->currentUser($user)->toArray(),
        ]);
    }
}
