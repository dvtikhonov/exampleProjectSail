<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use App\DTO\Task\UpdateTaskDto;
use App\Enums\TaskStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация частичного обновления задачи и преобразование в UpdateTaskDto.
 */
class UpdateTaskRequest extends FormRequest
{
    /** Обновление доступно аутентифицированным пользователям. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Правила валидации полей обновления задачи.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'due_date' => ['sometimes', 'required', 'date', 'after_or_equal:today'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
        ];
    }

    /** Собирает DTO из провалидированных полей запроса. */
    public function toDto(): UpdateTaskDto
    {
        return UpdateTaskDto::fromValidated($this->validated());
    }
}
