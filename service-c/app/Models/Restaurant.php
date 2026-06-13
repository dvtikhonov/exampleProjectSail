<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'address',
    'is_active',
])]
class Restaurant extends Model
{
    protected $table = 'max_restaurants';

    /** @use HasFactory<RestaurantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<MenuCategory, $this>
     */
    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<Cart, $this>
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * @return HasMany<FoodOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(FoodOrder::class);
    }

    /**
     * @return HasMany<RestaurantCategoryDeliveryTier, $this>
     */
    public function deliveryTiers(): HasMany
    {
        return $this->hasMany(RestaurantCategoryDeliveryTier::class);
    }
}
