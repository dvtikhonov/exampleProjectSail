<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Dish;
use App\Models\FoodOrder;
use App\Models\MenuCategory;
use App\Models\Restaurant;

trait ResetsFoodDomainTables
{
    protected function resetFoodDomainTables(): void
    {
        FoodOrder::query()->delete();
        CartItem::query()->delete();
        Cart::query()->delete();
        Dish::query()->delete();
        MenuCategory::query()->delete();
        Restaurant::query()->delete();
    }
}
