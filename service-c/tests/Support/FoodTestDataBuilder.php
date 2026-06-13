<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\CustomerCategory;
use App\Models\Dish;
use App\Models\MaxUser;
use App\Models\MenuCategory;
use App\Models\Restaurant;
use App\Models\RestaurantCategoryDeliveryTier;

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

    public static function createCustomerCategory(
        string $name = 'Standard',
        int $sortOrder = 1,
        bool $isActive = true,
    ): CustomerCategory {
        return CustomerCategory::query()->create([
            'name' => $name,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ]);
    }

    /**
     * @param  list<array{min_items_total: float, delivery_cost: float}>  $tiers
     * @return list<RestaurantCategoryDeliveryTier>
     */
    public static function createDeliveryTiers(
        Restaurant $restaurant,
        CustomerCategory $customerCategory,
        array $tiers,
    ): array {
        $created = [];

        foreach ($tiers as $tier) {
            $created[] = RestaurantCategoryDeliveryTier::query()->create([
                'restaurant_id' => $restaurant->id,
                'customer_category_id' => $customerCategory->id,
                'min_items_total' => $tier['min_items_total'],
                'delivery_cost' => $tier['delivery_cost'],
            ]);
        }

        return $created;
    }

    /**
     * @param  list<array{min_items_total: float, delivery_cost: float}>|null  $tiers
     * @return array{
     *     restaurant: Restaurant,
     *     category: MenuCategory,
     *     dish: Dish,
     *     customer_category: CustomerCategory,
     *     delivery_tiers: list<RestaurantCategoryDeliveryTier>,
     * }
     */
    public static function createRestaurantWithDishAndDelivery(
        string $restaurantName = 'Test Bistro',
        string $dishName = 'Test Pasta',
        float $price = 250.00,
        string $customerCategoryName = 'Standard',
        ?array $tiers = null,
    ): array {
        $fixture = self::createRestaurantWithDish($restaurantName, $dishName, $price);
        $customerCategory = self::createCustomerCategory($customerCategoryName);
        $deliveryTiers = self::createDeliveryTiers(
            $fixture['restaurant'],
            $customerCategory,
            $tiers ?? [
                ['min_items_total' => 1000.00, 'delivery_cost' => 0.00],
                ['min_items_total' => 0.00, 'delivery_cost' => 200.00],
            ],
        );

        return [
            ...$fixture,
            'customer_category' => $customerCategory,
            'delivery_tiers' => $deliveryTiers,
        ];
    }

    public static function assignCustomerCategory(MaxUser $maxUser, CustomerCategory $category): MaxUser
    {
        $maxUser->update(['customer_category_id' => $category->id]);

        return $maxUser->fresh();
    }

    public static function createMaxUserWithCategory(
        CustomerCategory $category,
        int $maxUserId = 99_001,
        string $firstName = 'FoodTester',
    ): MaxUser {
        return MaxUser::query()->create([
            'max_user_id' => $maxUserId,
            'first_name' => $firstName,
            'customer_category_id' => $category->id,
        ]);
    }
}
