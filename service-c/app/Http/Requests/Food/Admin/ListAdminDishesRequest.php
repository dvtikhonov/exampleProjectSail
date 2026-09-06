<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use App\Enums\Food\Menu\AdminDishAvailabilityFilter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация query-параметров списка блюд для админки.
 */
class ListAdminDishesRequest extends FormRequest
{
    /**
     * Разрешает любой запрос.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Всегда ожидает JSON-ответ.
     */
    public function wantsJson(): bool
    {
        return true;
    }

    /**
     * Правила валидации фильтров списка блюд.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'restaurant_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'name' => ['nullable', 'string', 'max:255'],
            'availability' => [
                'nullable',
                'string',
                Rule::in(array_column(AdminDishAvailabilityFilter::cases(), 'value')),
            ],
        ];
    }

    /**
     * Сообщения об ошибках валидации.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'availability.in' => 'Некорректный availability. Используйте all, available или hidden.',
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
            'category_id' => 'категория',
            'name' => 'название',
            'availability' => 'доступность',
        ];
    }

    /**
     * Фильтр по ресторану или null.
     */
    public function restaurantId(): ?int
    {
        $value = $this->validated('restaurant_id');

        return $value !== null ? (int) $value : null;
    }

    /**
     * Фильтр по категории или null.
     */
    public function categoryId(): ?int
    {
        $value = $this->validated('category_id');

        return $value !== null ? (int) $value : null;
    }

    /**
     * Поиск по названию или null.
     */
    public function nameSearch(): ?string
    {
        $value = $this->validated('name');

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Режим отображения по availability (по умолчанию all).
     */
    public function availability(): AdminDishAvailabilityFilter
    {
        $value = $this->validated('availability');

        if (! is_string($value) || $value === '') {
            return AdminDishAvailabilityFilter::All;
        }

        return AdminDishAvailabilityFilter::from($value);
    }
}
