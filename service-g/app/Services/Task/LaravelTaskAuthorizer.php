<?php

declare(strict_types=1);

namespace App\Services\Task;

use App\Contracts\TaskAuthorizerInterface;
use App\Models\Task;
use Illuminate\Support\Facades\Gate;

/**
 * Реализация авторизации задач через Laravel Gate / TaskPolicy.
 */
class LaravelTaskAuthorizer implements TaskAuthorizerInterface
{
    public function authorizeViewAny(): void
    {
        Gate::authorize('viewAny', Task::class);
    }

    public function authorizeView(Task $task): void
    {
        Gate::authorize('view', $task);
    }

    public function authorizeCreate(): void
    {
        Gate::authorize('create', Task::class);
    }

    public function authorizeUpdate(Task $task): void
    {
        Gate::authorize('update', $task);
    }

    public function authorizeDelete(Task $task): void
    {
        Gate::authorize('delete', $task);
    }
}
