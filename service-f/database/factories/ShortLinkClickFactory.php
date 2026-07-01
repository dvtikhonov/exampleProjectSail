<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShortLink;
use App\Models\ShortLinkClick;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Фабрика записей журнала кликов.
 *
 * @extends Factory<ShortLinkClick>
 */
class ShortLinkClickFactory extends Factory
{
    protected $model = ShortLinkClick::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'short_link_id' => ShortLink::factory(),
            'ip_address' => fake()->ipv4(),
            'visited_at' => now(),
        ];
    }
}
