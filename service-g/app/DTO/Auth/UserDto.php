<?php

declare(strict_types=1);

namespace App\DTO\Auth;

use App\Models\User;

/**
 * DTO пользователя для JSON-ответов API (без чувствительных полей).
 */
readonly class UserDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $role,
    ) {}

    /** Создаёт DTO из Eloquent-модели. */
    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $user->role->value,
        );
    }

    /**
     * Преобразует DTO в массив для JSON-ответа.
     *
     * @return array{id: int, name: string, email: string, role: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
        ];
    }
}
