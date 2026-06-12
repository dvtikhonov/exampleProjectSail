<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Dish;
use App\Models\MenuCategory;
use App\Models\Restaurant;

class FoodTestDataBuilder
{
    /**
     * @return array{restaurant: Restaurant, category: MenuCategory, dish: Dish}
     */
    public static function createRestaurantWithDish(
        string $restaurantName = 'Test Bistro',
        string $dishName = 'Test Pasta',
        float $price = 250.00,
    ): array {
        $restaurant = Restaurant::factory()->create([
            'name' => $restaurantName,
        ]);

        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Main',
            'sort_order' => 1,
        ]);

        $dish = Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => $dishName,
            'price' => $price,
        ]);

        return [
            'restaurant' => $restaurant,
            'category' => $category,
            'dish' => $dish,
        ];
    }
}
