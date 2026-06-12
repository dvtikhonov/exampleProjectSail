<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dish;
use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dish>
 */
class DishFactory extends Factory
{
    protected $model = Dish::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_category_id' => MenuCategory::factory(),
            'name' => fake()->words(3, true),
            'image_url' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=200&h=200&fit=crop',
            'price' => fake()->randomFloat(2, 150, 1500),
            'is_available' => true,
        ];
    }

    public function unavailable(): static
    {
        return $this->state([
            'is_available' => false,
        ]);
    }
}
