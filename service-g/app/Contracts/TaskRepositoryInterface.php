<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\Task\CreateTaskPersistenceDto;
use App\DTO\Task\ListTasksQueryDto;
use App\DTO\Task\UpdateTaskDto;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TaskRepositoryInterface
{
    /**
     * Пагинированный список задач с фильтрацией.
     *
     * @param  int|null  $ownerUserId  Ограничение по владельцу; null — без фильтра (все задачи).
     * @return LengthAwarePaginator<int, Task>
     */
    public function paginate(ListTasksQueryDto $query, ?int $ownerUserId): LengthAwarePaginator;

    /** Ищет задачу по id или возвращает null. */
    public function findById(int $id): ?Task;

    /** Создаёт задачу с переданными атрибутами. */
    public function create(CreateTaskPersistenceDto $dto): Task;

    /** Обновляет задачу переданными атрибутами. */
    public function update(Task $task, UpdateTaskDto $dto): Task;

    /** Удаляет задачу. */
    public function delete(Task $task): void;
}
