<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    /**
     * Создаёт пользователя с переданными атрибутами.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User;

    /**
     * Ищет пользователя по email.
     */
    public function findByEmail(string $email): ?User;
}
