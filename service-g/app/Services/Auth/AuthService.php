<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\AuthServiceInterface;
use App\Contracts\LoginRateLimiterInterface;
use App\Contracts\SessionManagerInterface;
use App\Contracts\UserRepositoryInterface;
use App\Contracts\WebAuthenticatorInterface;
use App\DTO\Auth\CreateUserPersistenceDto;
use App\DTO\Auth\LoginUserDto;
use App\DTO\Auth\RegisterUserDto;
use App\DTO\Auth\UserDto;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Аутентификация SPA через Sanctum (cookie + session).
 */
class AuthService implements AuthServiceInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoginRateLimiterInterface $loginRateLimiter,
        private readonly WebAuthenticatorInterface $authenticator,
        private readonly SessionManagerInterface $sessionManager,
    ) {}

    public function register(RegisterUserDto $dto): UserDto
    {
        $user = $this->userRepository->create(
            CreateUserPersistenceDto::fromRegister($dto),
        );

        $this->authenticator->login($user);
        $this->sessionManager->regenerate();

        return UserDto::fromModel($user);
    }

    /**
     * @throws ValidationException
     */
    public function login(LoginUserDto $dto, ?string $clientIp): UserDto
    {
        $this->loginRateLimiter->ensureIsNotRateLimited($dto->email, $clientIp);

        if (! $this->authenticator->attempt(
            ['email' => $dto->email, 'password' => $dto->password],
            $dto->remember,
        )) {
            $this->loginRateLimiter->hit($dto->email, $clientIp);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $this->loginRateLimiter->clear($dto->email, $clientIp);
        $this->sessionManager->regenerate();

        $user = $this->authenticator->currentUser();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        return UserDto::fromModel($user);
    }

    public function logout(): void
    {
        $this->authenticator->logout();
        $this->sessionManager->invalidate();
        $this->sessionManager->regenerateToken();
    }

    public function currentUser(User $user): UserDto
    {
        return UserDto::fromModel($user);
    }
}
