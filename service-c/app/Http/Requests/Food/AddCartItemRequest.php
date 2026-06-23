<?php

declare(strict_types=1);

namespace App\Http\Requests\Food;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация запроса добавления блюда в корзину.
 */
class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'dish_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * Возвращает ID блюда из валидированных данных.
     */
    public function dishId(): int
    {
        return (int) $this->validated('dish_id');
    }

    /**
     * Возвращает количество из валидированных данных.
     */
    public function quantity(): int
    {
        return (int) $this->validated('quantity');
    }
}
