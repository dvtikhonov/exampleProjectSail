<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

/**
 * Валидация изменения количества позиции ручной корзины.
 */
class ManualUpdateCartItemRequest extends ManualOrderCustomerFormRequest
{
    /**
     * Правила валидации количества и клиента.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...$this->customerMaxUserIdRules(),
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * Возвращает новое количество из валидированных данных.
     */
    public function quantity(): int
    {
        return (int) $this->validated('quantity');
    }
}
