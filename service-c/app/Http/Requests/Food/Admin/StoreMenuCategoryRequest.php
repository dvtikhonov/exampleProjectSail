<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use App\DTO\Food\CreateMenuCategoryDto;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация создания категории меню.
 */
class StoreMenuCategoryRequest extends FormRequest
{
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
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_combo_available' => ['sometimes', 'boolean'],
        ];
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
            'sort_order' => 'порядок сортировки',
            'is_combo_available' => 'доступность в комбо',
        ];
    }

    /**
     * Собирает DTO создания категории меню.
     */
    public function toCreateDto(int $defaultSortOrder): CreateMenuCategoryDto
    {
        $validated = $this->validated();

        return new CreateMenuCategoryDto(
            restaurantId: (int) $validated['restaurant_id'],
            name: trim((string) $validated['name']),
            sortOrder: array_key_exists('sort_order', $validated)
                ? (int) $validated['sort_order']
                : $defaultSortOrder,
            isComboAvailable: (bool) ($validated['is_combo_available'] ?? true),
        );
    }
}
