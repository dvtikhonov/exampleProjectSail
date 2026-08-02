<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Food\Cart;
use App\Models\Food\CartItem;
use App\Models\Food\CustomerCategory;
use App\Models\Food\Dish;
use App\Models\Food\DishAvailabilityDate;
use App\Models\Food\FoodOrder;
use App\Models\Food\FoodOrderAdmin;
use App\Models\Food\MenuCategory;
use App\Models\Food\MenuCategoryAvailabilityOffset;
use App\Models\Food\Restaurant;
use App\Models\Food\RestaurantCategoryDeliveryTier;
use App\Models\Max\MaxUser;
use Illuminate\Support\Facades\DB;

trait ResetsFoodDomainTables
{
    /** Очищает таблицы домена еды перед тестом. */
    protected function resetFoodDomainTables(): void
    {
        FoodOrder::query()->delete();
        FoodOrderAdmin::query()->delete();
        CartItem::query()->delete();
        Cart::query()->delete();
        DishAvailabilityDate::query()->delete();
        Dish::query()->forceDelete();
        MenuCategoryAvailabilityOffset::query()->delete();
        MenuCategory::query()->forceDelete();
        RestaurantCategoryDeliveryTier::query()->delete();
        DB::table('personal_access_tokens')->delete();
        MaxUser::query()->delete();
        CustomerCategory::query()->forceDelete();
        Restaurant::query()->forceDelete();
    }
}
