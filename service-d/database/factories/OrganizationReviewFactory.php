<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationReview>
 */
class OrganizationReviewFactory extends Factory
{
    protected $model = OrganizationReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'external_review_id' => fake()->uuid(),
            'author_name' => fake()->name(),
            'published_at' => fake()->dateTimeBetween('-1 year'),
            'text' => fake()->paragraph(),
            'rating' => fake()->numberBetween(1, 5),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
