<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Food\CustomerCategoryName;
use App\Models\CustomerCategory;
use App\Models\MaxUser;
use App\Models\Restaurant;
use App\Models\RestaurantCategoryDeliveryTier;
use Illuminate\Database\Seeder;

class CustomerCategorySeeder extends Seeder
{
    public function run(): void
    {
        $standard = CustomerCategory::query()->firstOrCreate(
            ['name' => CustomerCategoryName::Standard->value],
            ['sort_order' => 1, 'is_active' => true],
        );

        $vip = CustomerCategory::query()->firstOrCreate(
            ['name' => 'VIP'],
            ['sort_order' => 2, 'is_active' => true],
        );

        $tierMatrix = [
            'standard' => [
                ['min_items_total' => 1000.00, 'delivery_cost' => 0.00],
                ['min_items_total' => 0.00, 'delivery_cost' => 200.00],
            ],
            'vip' => [
                ['min_items_total' => 500.00, 'delivery_cost' => 0.00],
                ['min_items_total' => 0.00, 'delivery_cost' => 100.00],
            ],
        ];

        Restaurant::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->each(function (Restaurant $restaurant) use ($tierMatrix, $standard, $vip): void {
                foreach ($tierMatrix['standard'] as $tier) {
                    RestaurantCategoryDeliveryTier::query()->updateOrCreate(
                        [
                            'restaurant_id' => $restaurant->id,
                            'customer_category_id' => $standard->id,
                            'min_items_total' => $tier['min_items_total'],
                        ],
                        ['delivery_cost' => $tier['delivery_cost']],
                    );
                }

                foreach ($tierMatrix['vip'] as $tier) {
                    RestaurantCategoryDeliveryTier::query()->updateOrCreate(
                        [
                            'restaurant_id' => $restaurant->id,
                            'customer_category_id' => $vip->id,
                            'min_items_total' => $tier['min_items_total'],
                        ],
                        ['delivery_cost' => $tier['delivery_cost']],
                    );
                }
            });

        MaxUser::query()->updateOrCreate(
            ['max_user_id' => 1001],
            [
                'first_name' => 'Demo',
                'last_name' => 'Стандарт',
                'username' => 'demo_standard',
                'language_code' => 'ru',
                'customer_category_id' => $standard->id,
            ],
        );

        MaxUser::query()->updateOrCreate(
            ['max_user_id' => 1002],
            [
                'first_name' => 'Demo',
                'last_name' => 'VIP',
                'username' => 'demo_vip',
                'language_code' => 'ru',
                'customer_category_id' => $vip->id,
            ],
        );
    }
}
