<?php

declare(strict_types=1);

namespace App\Http\Requests\Food;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Валидация запроса добавления блюда в корзину.
 */
class AddCartItemRequest extends FormRequest
{
    /**
     * Разрешает выполнение запроса.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации позиции корзины.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'dish_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'combo_ref' => ['nullable', 'uuid', 'required_with:combo_partner_dish_id'],
            'combo_partner_dish_id' => ['nullable', 'integer', 'min:1', 'exists:max_dishes,id', 'required_with:combo_ref'],
        ];
    }

    /**
     * Дополнительная проверка парности полей комбо-метаданных.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasComboRef = $this->filled('combo_ref');
            $hasPartner = $this->filled('combo_partner_dish_id');

            if ($hasComboRef xor $hasPartner) {
                $validator->errors()->add(
                    'combo_ref',
                    'Combo metadata fields combo_ref and combo_partner_dish_id must be provided together.',
                );
            }
        });
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

    /**
     * Идентификатор комбо-пары или null для обычной позиции.
     */
    public function comboRef(): ?string
    {
        $value = $this->validated('combo_ref');

        return is_string($value) ? $value : null;
    }

    /**
     * ID второго блюда комбо-пары или null для обычной позиции.
     */
    public function comboPartnerDishId(): ?int
    {
        $value = $this->validated('combo_partner_dish_id');

        return $value !== null ? (int) $value : null;
    }

    /**
     * Запрос содержит метаданные комбо-пары.
     */
    public function hasComboMetadata(): bool
    {
        return $this->comboRef() !== null;
    }
}
