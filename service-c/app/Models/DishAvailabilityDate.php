<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'dish_id',
    'available_date',
])]
/**
 * Дата доступности блюда в графике (таблица max_dish_availability_dates).
 */
class DishAvailabilityDate extends Model
{
    protected $table = 'max_dish_availability_dates';

    /**
     * Возвращает приведения атрибутов модели.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_date' => 'date',
        ];
    }

    /**
     * Связь с блюдом.
     *
     * @return BelongsTo<Dish, $this>
     */
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }
}
