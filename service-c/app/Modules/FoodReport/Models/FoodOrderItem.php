<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Models;

use App\Models\Food\FoodOrder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'restaurant_id',
    'report_date',
    'dish_id',
    'dish_name',
    'unit_price',
    'quantity',
    'line_total',
])]
/**
 * Нормализованная позиция выполненного заказа (таблица max_food_order_items, вариант B).
 */
class FoodOrderItem extends Model
{
    protected $table = 'max_food_order_items';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'restaurant_id' => 'integer',
            'report_date' => 'date',
            'dish_id' => 'integer',
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<FoodOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(FoodOrder::class, 'order_id');
    }
}
