<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * Авторизация операций с задачами.
 *
 * user — только свои задачи.
 * admin — просмотр всех задач; редактирование и удаление только своих.
 */
class TaskPolicy
{
    /** Любой аутентифицированный пользователь может просматривать список. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Просмотр: владелец или admin. */
    public function view(User $user, Task $task): bool
    {
        return $user->isAdmin() || $task->user_id === $user->id;
    }

    /** Создание доступно любому аутентифицированному пользователю. */
    public function create(User $user): bool
    {
        return true;
    }

    /** Обновление: только владелец (в том числе для admin). */
    public function update(User $user, Task $task): bool
    {
        return $task->user_id === $user->id;
    }

    /** Удаление: только владелец (в том числе для admin). */
    public function delete(User $user, Task $task): bool
    {
        return $task->user_id === $user->id;
    }
}
