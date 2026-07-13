<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use App\DTO\Task\ListTasksQueryDto;
use App\Enums\TaskStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация query-параметров списка задач и преобразование в ListTasksQueryDto.
 */
class ListTasksRequest extends FormRequest
{
    /** Список доступен аутентифицированным пользователям. */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** Пустые query-строки приводим к null до валидации. */
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['status', 'search', 'sort', 'direction'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $merge[$field] = null;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * Правила валидации query-параметров списка.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::enum(TaskStatus::class)],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort' => ['sometimes', 'nullable', Rule::in(['due_date', 'status', 'created_at'])],
            'direction' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** Собирает DTO из провалидированных query-параметров. */
    public function toDto(): ListTasksQueryDto
    {
        $validated = $this->validated();

        $status = $validated['status'] ?? null;
        if (is_string($status)) {
            $status = TaskStatus::from($status);
        }

        return new ListTasksQueryDto(
            status: $status instanceof TaskStatus ? $status : null,
            search: isset($validated['search']) ? (string) $validated['search'] : null,
            sort: (string) ($validated['sort'] ?? 'created_at'),
            direction: (string) ($validated['direction'] ?? 'desc'),
            page: (int) ($validated['page'] ?? 1),
            perPage: (int) ($validated['per_page'] ?? 5),
        );
    }
}
