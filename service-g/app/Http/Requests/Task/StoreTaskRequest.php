<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use App\DTO\Task\CreateTaskDto;
use App\Enums\TaskStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация создания задачи и преобразование в CreateTaskDto.
 */
class StoreTaskRequest extends FormRequest
{
    /** Создание доступно аутентифицированным пользователям. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Правила валидации полей создания задачи.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ];
    }

    /** Собирает DTO из провалидированных полей запроса. */
    public function toDto(): CreateTaskDto
    {
        $validated = $this->validated();

        return new CreateTaskDto(
            title: (string) $validated['title'],
            description: isset($validated['description']) ? (string) $validated['description'] : null,
            dueDate: (string) $validated['due_date'],
            status: $validated['status'] instanceof TaskStatus
                ? $validated['status']
                : TaskStatus::from((string) $validated['status']),
        );
    }
}
