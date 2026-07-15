<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'address',
    'is_active',
])]
/**
 * Ресторан с меню и тарифами доставки (таблица max_restaurants).
 */
class Restaurant extends Model
{
    protected $table = 'max_restaurants';

    /** @use HasFactory<RestaurantFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Возвращает приведения атрибутов модели.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Связь с категориями меню.
     *
     * @return HasMany<MenuCategory, $this>
     */
    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class)->orderBy('sort_order');
    }

    /**
     * Связь с корзинами ресторана.
     *
     * @return HasMany<Cart, $this>
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * Связь с заказами ресторана.
     *
     * @return HasMany<FoodOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(FoodOrder::class);
    }

    /**
     * Связь с тарифами доставки.
     *
     * @return HasMany<RestaurantCategoryDeliveryTier, $this>
     */
    public function deliveryTiers(): HasMany
    {
        return $this->hasMany(RestaurantCategoryDeliveryTier::class);
    }
}
