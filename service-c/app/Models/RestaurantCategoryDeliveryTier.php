<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'restaurant_id',
    'customer_category_id',
    'min_items_total',
    'delivery_cost',
])]
/**
 * Тариф доставки для пары ресторан + категория клиента.
 */
class RestaurantCategoryDeliveryTier extends Model
{
    protected $table = 'max_restaurant_category_delivery_tiers';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_items_total' => 'decimal:2',
            'delivery_cost' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return BelongsTo<CustomerCategory, $this>
     */
    public function customerCategory(): BelongsTo
    {
        return $this->belongsTo(CustomerCategory::class, 'customer_category_id');
    }
}
