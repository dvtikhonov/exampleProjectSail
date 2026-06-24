<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerCategory;
use App\Models\Dish;
use App\Models\FoodOrder;
use App\Models\FoodOrderAdmin;
use App\Models\MaxUser;
use App\Models\MenuCategory;
use App\Models\Restaurant;
use App\Models\RestaurantCategoryDeliveryTier;
use Illuminate\Support\Facades\DB;

trait ResetsFoodDomainTables
{
    protected function resetFoodDomainTables(): void
    {
        FoodOrder::query()->delete();
        FoodOrderAdmin::query()->delete();
        CartItem::query()->delete();
        Cart::query()->delete();
        Dish::query()->delete();
        MenuCategory::query()->delete();
        RestaurantCategoryDeliveryTier::query()->delete();
        DB::table('personal_access_tokens')->delete();
        MaxUser::query()->delete();
        CustomerCategory::query()->delete();
        Restaurant::query()->delete();
    }
}
