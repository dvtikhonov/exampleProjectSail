<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerCategory;
use App\Models\Dish;
use App\Models\DishAvailabilityDate;
use App\Models\FoodOrder;
use App\Models\FoodOrderAdmin;
use App\Models\MaxUser;
use App\Models\MenuCategory;
use App\Models\Restaurant;
use App\Models\RestaurantCategoryDeliveryTier;
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
        MenuCategory::query()->forceDelete();
        RestaurantCategoryDeliveryTier::query()->delete();
        DB::table('personal_access_tokens')->delete();
        MaxUser::query()->delete();
        CustomerCategory::query()->forceDelete();
        Restaurant::query()->forceDelete();
    }
}
