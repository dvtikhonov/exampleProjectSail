<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use App\DTO\Food\DishAvailabilityChangeDto;
use App\DTO\Food\DishAvailabilityUpdateDto;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация пакетного обновления графика доступности блюд.
 */
class SyncDishAvailabilityScheduleRequest extends FormRequest
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
            'changes' => ['required', 'array'],
            'changes.*.dish_id' => ['required', 'integer', 'min:1'],
            'changes.*.dates' => ['present', 'array'],
            'changes.*.dates.*' => ['date', 'date_format:Y-m-d'],
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
            'changes.required' => 'Передайте список изменений графика.',
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
            'changes' => 'изменения',
            'changes.*.dish_id' => 'блюдо',
            'changes.*.dates' => 'даты',
        ];
    }

    public function toDto(): DishAvailabilityUpdateDto
    {
        /** @var list<array{dish_id: int, dates: list<string>}> $changes */
        $changes = $this->validated('changes');

        return new DishAvailabilityUpdateDto(
            restaurantId: (int) $this->validated('restaurant_id'),
            categoryId: (int) $this->validated('category_id'),
            changes: array_map(
                static fn (array $change): DishAvailabilityChangeDto => new DishAvailabilityChangeDto(
                    dishId: (int) $change['dish_id'],
                    dates: array_values($change['dates']),
                ),
                $changes,
            ),
            dateFrom: $this->nullableDate('date_from'),
            dateTo: $this->nullableDate('date_to'),
        );
    }

    private function nullableDate(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
