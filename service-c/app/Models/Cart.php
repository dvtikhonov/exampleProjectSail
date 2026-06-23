<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Food\CartStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'max_user_id',
    'restaurant_id',
    'status',
    'delivery_address',
])]
/**
 * Корзина пользователя MAX перед оформлением заказа (таблица max_carts).
 */
class Cart extends Model
{
    protected $table = 'max_carts';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_user_id' => 'integer',
            'status' => CartStatus::class,
        ];
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

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * @return HasOne<FoodOrder, $this>
     */
    public function order(): HasOne
    {
        return $this->hasOne(FoodOrder::class);
    }
}
