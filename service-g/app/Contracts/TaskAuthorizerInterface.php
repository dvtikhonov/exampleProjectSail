<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Task;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Авторизация операций с задачами (делегирование в TaskPolicy).
 */
interface TaskAuthorizerInterface
{
    /**
     * @throws AuthorizationException
     */
    public function authorizeViewAny(): void;

    /**
     * @throws AuthorizationException
     */
    public function authorizeView(Task $task): void;

    /**
     * @throws AuthorizationException
     */
    public function authorizeCreate(): void;

    /**
     * @throws AuthorizationException
     */
    public function authorizeUpdate(Task $task): void;

    /**
     * @throws AuthorizationException
     */
    public function authorizeDelete(Task $task): void;
}
