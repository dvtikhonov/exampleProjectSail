<?php

declare(strict_types=1);

namespace App\Http\Requests\Food;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация запроса изменения количества позиции корзины.
 */
class UpdateCartItemRequest extends FormRequest
{
    /**
     * Разрешает выполнение запроса.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации количества позиции.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
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
