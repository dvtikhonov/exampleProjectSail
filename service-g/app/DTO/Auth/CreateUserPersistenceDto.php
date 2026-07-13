<?php

declare(strict_types=1);

namespace App\DTO\Auth;

use App\Enums\UserRole;

/**
 * Типизированные атрибуты для создания пользователя в persistence-слое.
 */
readonly class CreateUserPersistenceDto
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public UserRole $role,
    ) {}

    /** Собирает persistence-DTO из данных регистрации. */
    public static function fromRegister(RegisterUserDto $dto, UserRole $role = UserRole::User): self
    {
        return new self(
            name: $dto->name,
            email: $dto->email,
            password: $dto->password,
            role: $role,
        );
    }

    /**
     * Преобразует DTO в атрибуты Eloquent.
     *
     * @return array{name: string, email: string, password: string, role: UserRole}
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'role' => $this->role,
        ];
    }
}
