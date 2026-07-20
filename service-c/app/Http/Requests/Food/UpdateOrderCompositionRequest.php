<?php

declare(strict_types=1);

namespace App\Http\Requests\Food;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Валидация запроса обновления состава заказа администратором.
 */
class UpdateOrderCompositionRequest extends FormRequest
{
    /**
     * Разрешает выполнение запроса.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации позиций состава.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.dish_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.combo_ref' => ['nullable', 'uuid', 'required_with:items.*.combo_partner_dish_id'],
            'items.*.combo_partner_dish_id' => [
                'nullable',
                'integer',
                'min:1',
                'required_with:items.*.combo_ref',
            ],
        ];
    }

    /**
     * Дополнительная проверка парности полей комбо-метаданных в каждой позиции.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $items = $this->input('items');

            if (! is_array($items)) {
                return;
            }

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $hasComboRef = array_key_exists('combo_ref', $item)
                    && $item['combo_ref'] !== null
                    && $item['combo_ref'] !== '';
                $hasPartner = array_key_exists('combo_partner_dish_id', $item)
                    && $item['combo_partner_dish_id'] !== null
                    && $item['combo_partner_dish_id'] !== '';

                if ($hasComboRef xor $hasPartner) {
                    $validator->errors()->add(
                        "items.{$index}.combo_ref",
                        'Combo metadata fields combo_ref and combo_partner_dish_id must be provided together.',
                    );
                }
            }
        });
    }

    /**
     * Сообщения об ошибках валидации состава.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Укажите хотя бы одну позицию состава.',
            'items.min' => 'Укажите хотя бы одну позицию состава.',
            'items.*.dish_id.required' => 'Укажите блюдо.',
            'items.*.quantity.required' => 'Укажите количество.',
            'items.*.quantity.min' => 'Количество должно быть не меньше 1.',
            'items.*.quantity.max' => 'Количество не должно превышать 99.',
        ];
    }

    /**
     * Человекочитаемые имена атрибутов.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items' => 'состав заказа',
            'items.*.dish_id' => 'блюдо',
            'items.*.quantity' => 'количество',
            'items.*.combo_ref' => 'идентификатор комбо',
            'items.*.combo_partner_dish_id' => 'партнёр комбо',
        ];
    }

    /**
     * Нормализованный список позиций для обновления состава.
     *
     * @return list<array{
     *     dish_id: int,
     *     quantity: int,
     *     combo_ref: string|null,
     *     combo_partner_dish_id: int|null
     * }>
     */
    public function items(): array
    {
        /** @var list<array<string, mixed>> $items */
        $items = $this->validated('items');

        return array_values(array_map(static function (array $item): array {
            $comboRef = $item['combo_ref'] ?? null;
            $partnerId = $item['combo_partner_dish_id'] ?? null;

            return [
                'dish_id' => (int) $item['dish_id'],
                'quantity' => (int) $item['quantity'],
                'combo_ref' => is_string($comboRef) ? $comboRef : null,
                'combo_partner_dish_id' => $partnerId !== null ? (int) $partnerId : null,
            ];
        }, $items));
    }
}
