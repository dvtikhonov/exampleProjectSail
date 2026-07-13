<?php

declare(strict_types=1);

namespace App\Services\Task;

use App\Contracts\TaskAuthorizerInterface;
use App\Contracts\TaskRepositoryInterface;
use App\Contracts\TaskServiceInterface;
use App\DTO\Task\CreateTaskDto;
use App\DTO\Task\CreateTaskPersistenceDto;
use App\DTO\Task\ListTasksQueryDto;
use App\DTO\Task\PaginatedTasksDto;
use App\DTO\Task\TaskDto;
use App\DTO\Task\UpdateTaskDto;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Бизнес-логика CRUD задач с делегированием авторизации в TaskPolicy.
 */
class TaskService implements TaskServiceInterface
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly TaskAuthorizerInterface $taskAuthorizer,
    ) {}

    public function list(User $user, ListTasksQueryDto $query): PaginatedTasksDto
    {
        $this->taskAuthorizer->authorizeViewAny();

        $ownerUserId = $user->isAdmin() ? null : $user->id;
        $paginator = $this->taskRepository->paginate($query, $ownerUserId);

        return PaginatedTasksDto::fromPaginator($paginator, $user->isAdmin());
    }

    /**
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     */
    public function show(User $user, int $id): TaskDto
    {
        $task = $this->findTaskOrFail($id);
        $this->taskAuthorizer->authorizeView($task);

        return TaskDto::fromModel($task, $user->isAdmin());
    }

    /**
     * @throws AuthorizationException
     */
    public function create(User $user, CreateTaskDto $dto): TaskDto
    {
        $this->taskAuthorizer->authorizeCreate();

        $task = $this->taskRepository->create(
            CreateTaskPersistenceDto::forOwner($user->id, $dto),
        );

        $task->load('user');

        return TaskDto::fromModel($task, $user->isAdmin());
    }

    /**
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     */
    public function update(User $user, int $id, UpdateTaskDto $dto): TaskDto
    {
        $task = $this->findTaskOrFail($id);
        $this->taskAuthorizer->authorizeUpdate($task);

        $task = $this->taskRepository->update($task, $dto);

        return TaskDto::fromModel($task, $user->isAdmin());
    }

    /**
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     */
    public function delete(User $user, int $id): void
    {
        $task = $this->findTaskOrFail($id);
        $this->taskAuthorizer->authorizeDelete($task);

        $this->taskRepository->delete($task);
    }

    /**
     * @throws ModelNotFoundException
     */
    private function findTaskOrFail(int $id): Task
    {
        $task = $this->taskRepository->findById($id);

        if ($task === null) {
            throw (new ModelNotFoundException)->setModel(Task::class, [$id]);
        }

        return $task;
    }
}
