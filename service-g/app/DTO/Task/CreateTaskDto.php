<?php

declare(strict_types=1);

namespace App\DTO\Task;

use App\Enums\TaskStatus;

/**
 * Данные для создания задачи.
 */
readonly class CreateTaskDto
{
    public function __construct(
        public string $title,
        public ?string $description,
        public string $dueDate,
        public TaskStatus $status,
    ) {}
}
