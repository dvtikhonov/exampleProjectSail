<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class DishImageApiTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    public function test_dish_image_endpoint_proxies_remote_url_without_auth(): void
    {
        Http::fake([
            'images.unsplash.com/*' => Http::response('fake-image-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $fixture = FoodTestDataBuilder::createRestaurantWithDish();

        $this->get('/api/food/dishes/'.$fixture['dish']->id.'/image')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_dish_image_endpoint_returns_not_found_when_image_missing(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $fixture['dish']->update(['image_url' => null]);

        $this->get('/api/food/dishes/'.$fixture['dish']->id.'/image')
            ->assertNotFound();
    }

    public function test_dish_image_endpoint_serves_local_public_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('dishes/test.jpg', 'local-image-bytes');

        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $fixture['dish']->update(['image_url' => 'dishes/test.jpg']);

        $this->get('/api/food/dishes/'.$fixture['dish']->id.'/image')
            ->assertOk();
    }
}
