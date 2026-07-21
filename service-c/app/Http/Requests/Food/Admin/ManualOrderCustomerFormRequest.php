<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Базовая валидация контекста клиента для ручных заказов.
 */
abstract class ManualOrderCustomerFormRequest extends FormRequest
{
    /**
     * Разрешает выполнение запроса.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации идентификатора клиента.
     *
     * @return array<string, array<int, string>>
     */
    protected function customerMaxUserIdRules(): array
    {
        return [
            'max_user_id' => ['required', 'integer', 'min:1', 'exists:max_users,max_user_id'],
        ];
    }

    /**
     * Возвращает ID клиента из валидированных данных.
     */
    public function customerMaxUserId(): int
    {
        return (int) $this->validated('max_user_id');
    }
}
