<?php

declare(strict_types=1);

namespace App\DTO\Task;

use App\Models\Task;

/**
 * DTO задачи для JSON-ответов API.
 */
readonly class TaskDto
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public ?string $dueDate,
        public string $status,
        public string $createdAt,
        public string $updatedAt,
        public int $userId,
        public ?string $ownerName = null,
    ) {}

    /** Создаёт DTO из Eloquent-модели. */
    public static function fromModel(Task $task, bool $includeOwnerName = false): self
    {
        return new self(
            id: $task->id,
            title: $task->title,
            description: $task->description,
            dueDate: $task->due_date?->format('Y-m-d'),
            status: $task->status->value,
            createdAt: $task->created_at->toIso8601String(),
            updatedAt: $task->updated_at->toIso8601String(),
            userId: $task->user_id,
            ownerName: $includeOwnerName && $task->relationLoaded('user')
                ? $task->user->name
                : null,
        );
    }

    /**
     * Преобразует DTO в массив для JSON-ответа.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'due_date' => $this->dueDate,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'user_id' => $this->userId,
        ];

        if ($this->ownerName !== null) {
            $data['owner_name'] = $this->ownerName;
        }

        return $data;
    }
}
