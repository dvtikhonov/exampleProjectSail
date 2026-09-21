<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class DishImageApiTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->resetFoodDomainTables();
    }

    /** Эндпоинт изображения блюда возвращает 404 для удалённого URL. */
    public function test_dish_image_endpoint_returns_not_found_for_remote_url(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $fixture['dish']->update(['image_url' => 'https://images.unsplash.com/photo-example']);

        $this->get('/api/food/dishes/'.$fixture['dish']->id.'/image')
            ->assertNotFound();
    }

    /** Эндпоинт изображения блюда возвращает 404, если файл отсутствует. */
    public function test_dish_image_endpoint_returns_not_found_when_image_missing(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $fixture['dish']->update(['image_url' => null]);

        $this->get('/api/food/dishes/'.$fixture['dish']->id.'/image')
            ->assertNotFound();
    }

    /** Эндпоинт изображения блюда отдаёт локальный public-файл. */
    public function test_dish_image_endpoint_serves_local_public_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('dishes/test.jpg', 'local-image-bytes');

        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $fixture['dish']->update(['image_url' => 'dishes/test.jpg']);

        $this->get('/api/food/dishes/'.$fixture['dish']->id.'/image')
            ->assertOk();
    }

    /** Эндпоинт отклоняет path traversal через ../.env. */
    public function test_dish_image_endpoint_rejects_parent_directory_traversal(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $fixture['dish']->update(['image_url' => '../.env']);

        $this->get('/api/food/dishes/'.$fixture['dish']->id.'/image')
            ->assertNotFound();
    }

    /** Эндпоинт отклоняет traversal внутри префикса dishes/. */
    public function test_dish_image_endpoint_rejects_nested_directory_traversal(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $fixture['dish']->update(['image_url' => 'dishes/../../.env']);

        $this->get('/api/food/dishes/'.$fixture['dish']->id.'/image')
            ->assertNotFound();
    }

    /** Эндпоинт отклоняет абсолютный путь к файлу. */
    public function test_dish_image_endpoint_rejects_absolute_path(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $fixture['dish']->update(['image_url' => '/etc/passwd']);

        $this->get('/api/food/dishes/'.$fixture['dish']->id.'/image')
            ->assertNotFound();
    }

    /** Эндпоинт изображения блюда отдаёт файл для мягко удалённого блюда. */
    public function test_dish_image_endpoint_serves_image_for_soft_deleted_dish(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('dishes/deleted.jpg', 'deleted-dish-image');

        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $fixture['dish']->update(['image_url' => 'dishes/deleted.jpg']);
        $fixture['dish']->delete();

        $this->get('/api/food/dishes/'.$fixture['dish']->id.'/image')
            ->assertOk();
    }

    /** GET /api/food/dishes/{dish}/image защищён named throttle food-dish-image. */
    public function test_dish_image_route_has_throttle_middleware(): void
    {
        $route = collect(Route::getRoutes())->first(
            static function ($route): bool {
                return $route->uri() === 'api/food/dishes/{dish}/image'
                    && in_array('GET', $route->methods(), true);
            },
        );

        $this->assertNotNull($route);
        $this->assertContains('throttle:food-dish-image', $route->gatherMiddleware());
    }

    /** Эндпоинт изображения блюда возвращает 429 при превышении rate limit (30/мин). */
    public function test_dish_image_endpoint_returns_too_many_requests_when_rate_limited(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $fixture['dish']->update(['image_url' => null]);
        $url = '/api/food/dishes/'.$fixture['dish']->id.'/image';

        for ($i = 0; $i < 30; $i++) {
            $this->get($url)->assertNotFound();
        }

        $this->get($url)->assertTooManyRequests();
    }
}
