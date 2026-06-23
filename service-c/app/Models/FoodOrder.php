<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Food\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cart_id',
    'max_user_id',
    'restaurant_id',
    'status',
    'total',
    'delivery_address',
    'delivery_cost',
    'items_total',
    'items_snapshot',
])]
/**
 * Заказ еды пользователя MAX mini-app (таблица max_food_orders).
 */
class FoodOrder extends Model
{
    protected $table = 'max_food_orders';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_user_id' => 'integer',
            'status' => OrderStatus::class,
            'total' => 'decimal:2',
            'delivery_cost' => 'decimal:2',
            'items_total' => 'decimal:2',
            'items_snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<MaxUser, $this>
     */
    public function maxUser(): BelongsTo
    {
        return $this->belongsTo(MaxUser::class, 'max_user_id', 'max_user_id');
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
