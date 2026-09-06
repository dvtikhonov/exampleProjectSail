<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use App\DTO\Food\Menu\CreateMenuCategoryDto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Валидация создания категории меню.
 */
class StoreMenuCategoryRequest extends FormRequest
{
    use ValidatesMenuCategoryAvailabilityOffsets;

    /**
     * Разрешает выполнение запроса.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации создания категории меню.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'restaurant_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'is_combo_available' => ['sometimes', 'boolean'],
            ...$this->availabilityOffsetRules(),
        ];
    }

    /**
     * Дополнительная проверка уникальности дней недели между правилами.
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateAvailabilityOffsetWeekdayUniqueness($validator);
    }

    /**
     * Сообщения об ошибках валидации категории.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'restaurant_id.required' => 'Выберите ресторан.',
            'name.required' => 'Укажите название категории.',
            ...$this->availabilityOffsetMessages(),
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
            'restaurant_id' => 'ресторан',
            'name' => 'название',
            'is_combo_available' => 'доступность в комбо',
            ...$this->availabilityOffsetAttributes(),
        ];
    }

    /**
     * Собирает DTO создания категории меню.
     */
    public function toCreateDto(): CreateMenuCategoryDto
    {
        $validated = $this->validated();

        return new CreateMenuCategoryDto(
            restaurantId: (int) $validated['restaurant_id'],
            name: trim((string) $validated['name']),
            isComboAvailable: (bool) ($validated['is_combo_available'] ?? true),
            availabilityOffsets: $this->mapAvailabilityOffsetsFromValidated($validated),
        );
    }
}
