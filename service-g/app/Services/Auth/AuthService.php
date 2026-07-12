<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\UserRepositoryInterface;
use App\DTO\Auth\LoginUserDto;
use App\DTO\Auth\RegisterUserDto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Аутентификация SPA через Sanctum (cookie + session).
 */
class AuthService
{
    /** Внедряет репозиторий пользователей и rate limiter входа. */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoginRateLimiter $loginRateLimiter,
    ) {}

    /** Регистрирует пользователя и открывает сессию. */
    public function register(RegisterUserDto $dto, Request $request): User
    {
        $user = $this->userRepository->create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => $dto->password,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return $user;
    }

    /**
     * Выполняет вход по email/password.
     *
     * @throws ValidationException
     */
    public function login(LoginUserDto $dto, Request $request): User
    {
        $this->loginRateLimiter->ensureIsNotRateLimited($dto->email, $request->ip(), $request);

        if (! Auth::attempt(
            ['email' => $dto->email, 'password' => $dto->password],
            $dto->remember,
        )) {
            $this->loginRateLimiter->hit($dto->email, $request->ip());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $this->loginRateLimiter->clear($dto->email, $request->ip());
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /** Завершает сессию пользователя. */
    public function logout(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
