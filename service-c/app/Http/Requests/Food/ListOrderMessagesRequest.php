<?php

declare(strict_types=1);

namespace App\Http\Requests\Food;

use App\Http\Requests\Food\Concerns\AuthorizesCustomerResourceOwnership;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация запроса списка сообщений чата заказа.
 */
class ListOrderMessagesRequest extends FormRequest
{
    use AuthorizesCustomerResourceOwnership;

    /**
     * Владелец заказа или активный админ; отсутствующий заказ — в сервис → 404.
     */
    public function authorize(): bool
    {
        $user = $this->authenticatedMaxUser();

        if ($user === null) {
            return false;
        }

        $canAccess = $this->ownershipGuard()->canAccessOrderChat(
            $user,
            $this->routeResourceId('order'),
        );

        return $canAccess !== false;
    }

    /**
     * Правила валидации query-параметров списка сообщений.
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
