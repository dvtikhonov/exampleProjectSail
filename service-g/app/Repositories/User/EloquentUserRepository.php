<?php

declare(strict_types=1);

namespace App\Repositories\User;

use App\Contracts\UserRepositoryInterface;
use App\DTO\Auth\CreateUserPersistenceDto;
use App\Models\User;

/**
 * Eloquent-реализация доступа к пользователям.
 */
class EloquentUserRepository implements UserRepositoryInterface
{
    public function create(CreateUserPersistenceDto $dto): User
    {
        return User::query()->create($dto->toAttributes());
    }
}
