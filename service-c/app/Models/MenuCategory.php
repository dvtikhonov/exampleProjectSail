<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MenuCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'restaurant_id',
    'name',
    'sort_order',
])]
/**
 * Категория меню ресторана (таблица max_menu_categories).
 */
class MenuCategory extends Model
{
    protected $table = 'max_menu_categories';

    /** @use HasFactory<MenuCategoryFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return HasMany<Dish, $this>
     */
    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }
}
