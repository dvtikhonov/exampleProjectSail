<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Dish;
use App\Models\MenuCategory;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;

class RestaurantSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRestaurant(
            name: 'Пицца Макс',
            address: 'ул. Ленина, 10',
            categories: [
                'Пицца' => [
                    ['Маргарита', 450.00, 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=200&h=200&fit=crop'],
                    ['Пепперони', 520.00, 'https://images.unsplash.com/photo-1628840042765-356cda07504e?w=200&h=200&fit=crop'],
                    ['Четыре сыра', 580.00, 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=200&h=200&fit=crop'],
                ],
                'Напитки' => [
                    ['Кола 0.5л', 120.00, 'https://images.unsplash.com/photo-1629203851122-3726ecdf080e?w=200&h=200&fit=crop'],
                    ['Сок апельсиновый', 150.00, 'https://images.unsplash.com/photo-1600271886742-f049cd451bba?w=200&h=200&fit=crop'],
                ],
            ],
        );

        $this->seedRestaurant(
            name: 'Суши Бар',
            address: 'пр. Мира, 25',
            categories: [
                'Роллы' => [
                    ['Филадельфия', 390.00, 'https://images.unsplash.com/photo-1579584425555-c3ce17fd4351?w=200&h=200&fit=crop'],
                    ['Калифорния', 350.00, 'https://images.unsplash.com/photo-1617196034796-73dfa7b1fd56?w=200&h=200&fit=crop'],
                    ['Дракон', 470.00, 'https://images.unsplash.com/photo-1611143669185-af224c5e3252?w=200&h=200&fit=crop'],
                ],
                'Супы' => [
                    ['Мисо', 180.00, 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=200&h=200&fit=crop'],
                ],
            ],
        );

        $this->seedRestaurant(
            name: 'Бургер Хаус',
            address: 'ул. Гагарина, 7',
            categories: [
                'Бургеры' => [
                    ['Классический', 320.00, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=200&h=200&fit=crop'],
                    ['Чизбургер', 360.00, 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=200&h=200&fit=crop'],
                    ['Двойной бекон', 490.00, 'https://images.unsplash.com/photo-1594212699903-ec8a3eca50f5?w=200&h=200&fit=crop'],
                ],
                'Гарниры' => [
                    ['Картофель фри', 150.00, 'https://images.unsplash.com/photo-1573080496219-bb080dd4f877?w=200&h=200&fit=crop'],
                    ['Луковые кольца', 170.00, 'https://images.unsplash.com/photo-1639024371703-4b00acab9225?w=200&h=200&fit=crop'],
                ],
            ],
        );
    }

    /**
     * @param  array<string, list<array{0: string, 1: float, 2?: string|null}>>  $categories
     */
    private function seedRestaurant(string $name, string $address, array $categories): void
    {
        $restaurant = Restaurant::query()->create([
            'name' => $name,
            'address' => $address,
            'is_active' => true,
        ]);

        $sortOrder = 1;

        foreach ($categories as $categoryName => $dishes) {
            $category = MenuCategory::query()->create([
                'restaurant_id' => $restaurant->id,
                'name' => $categoryName,
                'sort_order' => $sortOrder,
            ]);

            foreach ($dishes as $dish) {
                Dish::query()->create([
                    'menu_category_id' => $category->id,
                    'name' => $dish[0],
                    'image_url' => $dish[2] ?? null,
                    'price' => $dish[1],
                    'is_available' => true,
                ]);
            }

            $sortOrder++;
        }
    }
}
