<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\Task\CreateTaskDto;
use App\DTO\Task\ListTasksQueryDto;
use App\DTO\Task\PaginatedTasksDto;
use App\DTO\Task\TaskDto;
use App\DTO\Task\UpdateTaskDto;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

interface TaskServiceInterface
{
    public function list(User $user, ListTasksQueryDto $query): PaginatedTasksDto;

    /**
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     */
    public function show(User $user, int $id): TaskDto;

    /**
     * @throws AuthorizationException
     */
    public function create(User $user, CreateTaskDto $dto): TaskDto;

    /**
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     */
    public function update(User $user, int $id, UpdateTaskDto $dto): TaskDto;

    /**
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     */
    public function delete(User $user, int $id): void;
}
