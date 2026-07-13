<?php

declare(strict_types=1);

namespace App\DTO\Task;

use App\Enums\TaskStatus;

/**
 * Частичное обновление задачи (только переданные поля).
 */
readonly class UpdateTaskDto
{
    /**
     * @param  list<string>  $presentKeys  Ключи полей, явно переданных в запросе.
     */
    public function __construct(
        private array $presentKeys,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $dueDate = null,
        public ?TaskStatus $status = null,
    ) {}

    /**
     * Собирает DTO из провалидированных полей запроса.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $status = null;
        if (array_key_exists('status', $validated)) {
            $status = $validated['status'] instanceof TaskStatus
                ? $validated['status']
                : TaskStatus::from((string) $validated['status']);
        }

        return new self(
            presentKeys: array_keys($validated),
            title: isset($validated['title']) ? (string) $validated['title'] : null,
            description: array_key_exists('description', $validated)
                ? ($validated['description'] !== null ? (string) $validated['description'] : null)
                : null,
            dueDate: isset($validated['due_date']) ? (string) $validated['due_date'] : null,
            status: $status,
        );
    }

    /**
     * Преобразует DTO в атрибуты Eloquent (только явно переданные поля).
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $attributes = [];

        if (in_array('title', $this->presentKeys, true)) {
            $attributes['title'] = $this->title;
        }

        if (in_array('description', $this->presentKeys, true)) {
            $attributes['description'] = $this->description;
        }

        if (in_array('due_date', $this->presentKeys, true)) {
            $attributes['due_date'] = $this->dueDate;
        }

        if (in_array('status', $this->presentKeys, true) && $this->status !== null) {
            $attributes['status'] = $this->status->value;
        }

        return $attributes;
    }
}
