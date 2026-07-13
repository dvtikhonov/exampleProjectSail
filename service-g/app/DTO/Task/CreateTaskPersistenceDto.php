<?php

declare(strict_types=1);

namespace App\DTO\Task;

use App\Enums\TaskStatus;

/**
 * Типизированные атрибуты для создания задачи в persistence-слое.
 */
readonly class CreateTaskPersistenceDto
{
    public function __construct(
        public int $userId,
        public string $title,
        public ?string $description,
        public string $dueDate,
        public TaskStatus $status,
    ) {}

    /** Собирает persistence-DTO из HTTP-DTO и id владельца. */
    public static function forOwner(int $userId, CreateTaskDto $dto): self
    {
        return new self(
            userId: $userId,
            title: $dto->title,
            description: $dto->description,
            dueDate: $dto->dueDate,
            status: $dto->status,
        );
    }

    /**
     * Преобразует DTO в атрибуты Eloquent.
     *
     * @return array{user_id: int, title: string, description: ?string, due_date: string, status: string}
     */
    public function toAttributes(): array
    {
        return [
            'user_id' => $this->userId,
            'title' => $this->title,
            'description' => $this->description,
            'due_date' => $this->dueDate,
            'status' => $this->status->value,
        ];
    }
}
