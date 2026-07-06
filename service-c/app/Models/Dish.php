<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Food\DishWeightUnit;
use Database\Factories\DishFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'menu_category_id',
    'name',
    'description',
    'weight',
    'weight_unit',
    'image_url',
    'price',
    'vat_rate',
    'is_available',
])]
/**
 * Блюдо меню ресторана (таблица max_dishes).
 */
class Dish extends Model
{
    protected $table = 'max_dishes';

    /** @use HasFactory<DishFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'decimal:3',
            'weight_unit' => DishWeightUnit::class,
            'price' => 'decimal:2',
            'vat_rate' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class);
    }

    /**
     * @return HasMany<CartItem, $this>
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * @return HasMany<DishAvailabilityDate, $this>
     */
    public function availabilityDates(): HasMany
    {
        return $this->hasMany(DishAvailabilityDate::class);
    }
}
