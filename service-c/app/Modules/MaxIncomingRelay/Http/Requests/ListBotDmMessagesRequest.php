<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация query-параметров ленты сообщений Bot DM.
 */
class ListBotDmMessagesRequest extends FormRequest
{
    /**
     * Разрешает запрос (роль — middleware food.order.admin:max_manager).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации after_id и limit.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'after_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Курсор после какого ID возвращать сообщения или null.
     */
    public function afterId(): ?int
    {
        if (! $this->filled('after_id')) {
            return null;
        }

        return (int) $this->validated('after_id');
    }

    /**
     * Лимит сообщений в ответе (по умолчанию 50).
     */
    public function limit(): int
    {
        return (int) ($this->validated('limit') ?? 50);
    }
}
