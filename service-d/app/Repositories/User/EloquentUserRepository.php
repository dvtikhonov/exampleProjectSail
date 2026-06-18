<?php

declare(strict_types=1);

namespace App\Repositories\User;

use App\Contracts\UserRepositoryInterface;
use App\Models\User;

/**
 * Eloquent-реализация доступа к пользователям.
 *
 * Инкапсулирует запросы к модели {@see User}; используется сервисным слоем аутентификации.
 */
class EloquentUserRepository implements UserRepositoryInterface
{
    /**
     * Создаёт пользователя с переданными атрибутами.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    /** Ищет пользователя по email. */
    public function findByEmail(string $email): ?User
    {
        return User::query()
            ->where('email', $email)
            ->first();
    }
}
