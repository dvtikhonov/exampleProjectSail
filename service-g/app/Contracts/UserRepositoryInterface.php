<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\Auth\CreateUserPersistenceDto;
use App\Models\User;

interface UserRepositoryInterface
{
    /** Создаёт пользователя с переданными атрибутами. */
    public function create(CreateUserPersistenceDto $dto): User;
}
