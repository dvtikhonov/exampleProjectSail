<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Фабрика коротких ссылок для тестов и сидов.
 *
 * @extends Factory<ShortLink>
 */
class ShortLinkFactory extends Factory
{
    protected $model = ShortLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_url' => fake()->url(),
            'code' => Str::lower(Str::random(fake()->numberBetween(4, 12))),
            'clicks_count' => 0,
        ];
    }
}
