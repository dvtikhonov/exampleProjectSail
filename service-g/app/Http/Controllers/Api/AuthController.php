<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API аутентификации для Nuxt SPA (Sanctum: cookie + session).
 */
class AuthController extends Controller
{
    /** Внедряет сервис аутентификации через DI. */
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * Регистрация нового пользователя.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->toDto(), $request);

        return response()->json([
            'user' => $this->serializeUser($user),
        ], 201);
    }

    /**
     * Вход по email и паролю.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->login($request->toDto(), $request);

        return response()->json([
            'user' => $this->serializeUser($user),
        ]);
    }

    /**
     * Выход из сессии.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request);

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    /**
     * Текущий аутентифицированный пользователь.
     */
    public function user(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => $this->serializeUser($user),
        ]);
    }

    /**
     * Сериализует пользователя для JSON-ответа (без чувствительных полей).
     *
     * @return array{id: int, name: string, email: string}
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
