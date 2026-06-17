<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source_url' => 'https://yandex.ru/maps/org/test/1234567890/',
            'canonical_url' => 'https://yandex.ru/maps/org/test/1234567890/',
            'yandex_org_id' => '1234567890',
            'name' => fake()->company(),
            'address' => fake()->address(),
            'average_rating' => fake()->randomFloat(2, 3, 5),
            'ratings_count' => fake()->numberBetween(10, 500),
            'reviews_count' => fake()->numberBetween(5, 200),
            'sync_status' => OrganizationSyncStatus::Pending,
            'sync_error' => null,
            'last_synced_at' => null,
        ];
    }
}
