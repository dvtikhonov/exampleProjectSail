<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация query-параметров графика доступности блюд.
 */
class ShowDishAvailabilityScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function wantsJson(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'restaurant_id' => ['required', 'integer', 'min:1'],
            'category_id' => ['required', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'restaurant_id.required' => 'Выберите ресторан.',
            'category_id.required' => 'Выберите категорию меню.',
            'date_to.after_or_equal' => 'Дата окончания не может быть раньше даты начала.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'restaurant_id' => 'ресторан',
            'category_id' => 'категория',
            'date_from' => 'дата начала',
            'date_to' => 'дата окончания',
        ];
    }

    public function restaurantId(): int
    {
        return (int) $this->validated('restaurant_id');
    }

    public function categoryId(): int
    {
        return (int) $this->validated('category_id');
    }

    public function dateFrom(): ?string
    {
        $value = $this->validated('date_from');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function dateTo(): ?string
    {
        $value = $this->validated('date_to');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
