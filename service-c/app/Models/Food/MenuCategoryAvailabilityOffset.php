<?php

declare(strict_types=1);

namespace App\Models\Food;

use App\Enums\Food\Menu\Weekday;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'menu_category_id',
    'group_key',
    'weekday',
    'offset_days',
])]
/**
 * Смещение доступности блюд категории по дню недели
 * (таблица max_menu_category_availability_offsets).
 *
 * Одна строка — один день недели в правиле; дни одного правила
 * объединяются общим group_key.
 */
class MenuCategoryAvailabilityOffset extends Model
{
    protected $table = 'max_menu_category_availability_offsets';

    /**
     * Возвращает приведения атрибутов модели.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => Weekday::class,
            'offset_days' => 'integer',
        ];
    }

    /**
     * Связь с категорией меню.
     *
     * @return BelongsTo<MenuCategory, $this>
     */
    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class);
    }
}
