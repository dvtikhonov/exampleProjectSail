<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Http\Requests;

/**
 * Валидация GET /api/food/admin/reports/top-dishes.
 *
 * Поля: date_from, date_to, restaurant_id, опц. date_axis, limit.
 */
class TopDishesReportRequest extends FoodReportFilterRequest
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 100;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'limit' => 'лимит позиций',
        ]);
    }

    public function limitPerDay(): int
    {
        $value = $this->validated('limit');

        return $value !== null ? (int) $value : self::DEFAULT_LIMIT;
    }
}
