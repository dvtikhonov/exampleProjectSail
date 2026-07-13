<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\Auth\LoginUserDto;
use App\DTO\Auth\RegisterUserDto;
use App\DTO\Auth\UserDto;
use App\Models\User;
use Illuminate\Validation\ValidationException;

interface AuthServiceInterface
{
    public function register(RegisterUserDto $dto): UserDto;

    /**
     * @throws ValidationException
     */
    public function login(LoginUserDto $dto, ?string $clientIp): UserDto;

    public function logout(): void;

    public function currentUser(User $user): UserDto;
}
