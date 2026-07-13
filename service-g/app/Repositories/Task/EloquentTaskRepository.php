<?php

declare(strict_types=1);

namespace App\Repositories\Task;

use App\Contracts\TaskRepositoryInterface;
use App\DTO\Task\CreateTaskPersistenceDto;
use App\DTO\Task\ListTasksQueryDto;
use App\DTO\Task\UpdateTaskDto;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent-реализация доступа к задачам.
 */
class EloquentTaskRepository implements TaskRepositoryInterface
{
    /**
     * Пагинированный список задач с фильтрацией и сортировкой.
     *
     * @return LengthAwarePaginator<int, Task>
     */
    public function paginate(ListTasksQueryDto $query, ?int $ownerUserId): LengthAwarePaginator
    {
        $builder = Task::query()->with('user');

        if ($ownerUserId !== null) {
            $builder->where('user_id', $ownerUserId);
        }

        if ($query->status !== null) {
            $builder->where('status', $query->status->value);
        }

        if ($query->search !== null && $query->search !== '') {
            $search = '%'.$query->search.'%';
            $builder->where(function (Builder $inner) use ($search): void {
                $inner->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        $sortColumn = match ($query->sort) {
            'due_date' => 'due_date',
            'status' => 'status',
            default => 'created_at',
        };

        return $builder
            ->orderBy($sortColumn, $query->direction)
            ->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    /** Ищет задачу по id с eager-load владельца. */
    public function findById(int $id): ?Task
    {
        return Task::query()
            ->with('user')
            ->find($id);
    }

    public function create(CreateTaskPersistenceDto $dto): Task
    {
        return Task::query()->create($dto->toAttributes());
    }

    public function update(Task $task, UpdateTaskDto $dto): Task
    {
        $task->update($dto->toAttributes());

        return $task->fresh(['user']);
    }

    /** Удаляет задачу из БД. */
    public function delete(Task $task): void
    {
        $task->delete();
    }
}
