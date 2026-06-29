<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Food\DishWeightUnit;
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
            'description' => fake()->optional(0.7)->sentence(),
            'weight' => fake()->randomFloat(3, 100, 500),
            'weight_unit' => fake()->randomElement(DishWeightUnit::cases())->value,
            'image_url' => 'dishes/seed/placeholder-1.jpg',
            'price' => fake()->randomFloat(2, 150, 1500),
            'vat_rate' => fake()->optional(0.5)->randomElement([5, 7, 10, 20, 22]),
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
