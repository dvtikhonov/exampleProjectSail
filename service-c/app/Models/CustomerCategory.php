<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'sort_order',
    'is_active',
])]
/**
 * Категория клиента для расчёта доставки (таблица max_customer_categories).
 */
class CustomerCategory extends Model
{
    use SoftDeletes;

    protected $table = 'max_customer_categories';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<MaxUser, $this>
     */
    public function maxUsers(): HasMany
    {
        return $this->hasMany(MaxUser::class, 'customer_category_id');
    }

    /**
     * @return HasMany<RestaurantCategoryDeliveryTier, $this>
     */
    public function deliveryTiers(): HasMany
    {
        return $this->hasMany(RestaurantCategoryDeliveryTier::class, 'customer_category_id');
    }
}
