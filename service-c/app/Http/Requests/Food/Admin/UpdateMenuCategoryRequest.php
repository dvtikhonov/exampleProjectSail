<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use App\DTO\Food\UpdateMenuCategoryDto;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация обновления категории меню.
 */
class UpdateMenuCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'restaurant_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_combo_available' => ['required', 'boolean'],
        ];
    }

    /**
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

    public function toUpdateDto(): UpdateMenuCategoryDto
    {
        $validated = $this->validated();

        return new UpdateMenuCategoryDto(
            restaurantId: (int) $validated['restaurant_id'],
            name: trim((string) $validated['name']),
            sortOrder: (int) $validated['sort_order'],
            isComboAvailable: (bool) $validated['is_combo_available'],
        );
    }
}
