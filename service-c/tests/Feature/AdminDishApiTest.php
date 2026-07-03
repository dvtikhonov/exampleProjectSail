<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\DishWeightUnit;
use App\Enums\Food\FoodOrderAdminRole;
use App\Models\Dish;
use App\Models\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\DishPhotoTestImageFactory;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\Support\ResolvesDishImageUrl;
use Tests\TestCase;

class AdminDishApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;
    use ResolvesDishImageUrl;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->resetFoodDomainTables();
    }

    public function test_admin_dishes_endpoints_return_unauthorized_without_token(): void
    {
        $this->getJson('/api/food/admin/dishes')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_admin_dishes_endpoints_return_forbidden_without_menu_manager_role(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/admin/dishes', $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_menu_manager_can_list_menu_categories(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $this->getJson('/api/food/admin/menu-categories', $auth['headers'])
            ->assertOk()
            ->assertJsonCount(1, 'categories')
            ->assertJsonPath('categories.0.id', $fixture['category']->id)
            ->assertJsonPath('categories.0.restaurant_name', $fixture['restaurant']->name);
    }

    public function test_menu_manager_can_filter_dishes_by_restaurant_and_category_id(): void
    {
        $first = FoodTestDataBuilder::createRestaurantWithDish('Sushi Bar', 'Miso Soup');
        $second = FoodTestDataBuilder::createRestaurantWithDish('Burger House', 'Classic Burger');

        $first['category']->update(['name' => 'Супы']);
        $second['category']->update(['name' => 'Супы']);

        $auth = $this->menuManagerAuth();

        $this->getJson(
            '/api/food/admin/dishes?restaurant_id='.$first['restaurant']->id.'&category_id='.$first['category']->id,
            $auth['headers'],
        )
            ->assertOk()
            ->assertJsonCount(1, 'dishes')
            ->assertJsonPath('dishes.0.name', 'Miso Soup')
            ->assertJsonPath('dishes.0.restaurant_id', $first['restaurant']->id);
    }

    public function test_menu_manager_can_filter_dishes_by_name(): void
    {
        FoodTestDataBuilder::createRestaurantWithDish('Sushi Bar', 'Miso Soup');
        FoodTestDataBuilder::createRestaurantWithDish('Sushi Bar', 'California Roll');
        FoodTestDataBuilder::createRestaurantWithDish('Burger House', 'Classic Burger');

        $auth = $this->menuManagerAuth();

        $this->getJson('/api/food/admin/dishes?name=roll', $auth['headers'])
            ->assertOk()
            ->assertJsonCount(1, 'dishes')
            ->assertJsonPath('dishes.0.name', 'California Roll');

        $this->getJson('/api/food/admin/dishes?name=Soup', $auth['headers'])
            ->assertOk()
            ->assertJsonCount(1, 'dishes')
            ->assertJsonPath('dishes.0.name', 'Miso Soup');
    }

    public function test_menu_manager_crud_happy_path(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'Original Dish');
        $auth = $this->menuManagerAuth();

        $this->getJson('/api/food/admin/dishes', $auth['headers'])
            ->assertOk()
            ->assertJsonCount(1, 'dishes')
            ->assertJsonPath('dishes.0.name', 'Original Dish');

        $createResponse = $this->postMultipart('/api/food/admin/dishes', $this->validStorePayload(
            menuCategoryId: $fixture['category']->id,
            name: 'Admin Created Dish',
        ), $auth['headers']);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('dish.name', 'Admin Created Dish')
            ->assertJsonPath('dish.menu_category_id', $fixture['category']->id)
            ->assertJsonPath('dish.weight', '250')
            ->assertJsonPath('dish.weight_unit', DishWeightUnit::Gram->value)
            ->assertJsonPath('dish.price', '399.00')
            ->assertJsonPath('dish.vat_rate', 10)
            ->assertJsonPath('dish.is_available', true);

        $dishId = (int) $createResponse->json('dish.id');
        $imagePath = Dish::query()->findOrFail($dishId)->image_url;

        $this->assertNotNull($imagePath);
        Storage::disk('public')->assertExists($imagePath);
        $createResponse->assertJsonPath('dish.image_url', $this->expectedDishImageUrl($dishId, $imagePath));

        $this->getJson("/api/food/admin/dishes/{$dishId}", $auth['headers'])
            ->assertOk()
            ->assertJsonPath('dish.id', $dishId)
            ->assertJsonPath('dish.name', 'Admin Created Dish')
            ->assertJsonPath('dish.image_url', $this->expectedDishImageUrl($dishId, $imagePath));

        $this->postMultipart("/api/food/admin/dishes/{$dishId}", [
            'name' => 'Renamed Dish',
            'price' => '450.00',
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('dish.name', 'Renamed Dish')
            ->assertJsonPath('dish.price', '450.00')
            ->assertJsonPath('dish.image_url', $this->expectedDishImageUrl($dishId, $imagePath));

        $this->assertSame($imagePath, Dish::query()->findOrFail($dishId)->image_url);

        $this->deleteJson("/api/food/admin/dishes/{$dishId}", [], $auth['headers'])
            ->assertNoContent();

        $this->assertSoftDeleted('max_dishes', ['id' => $dishId]);
    }

    public function test_deleted_dish_is_hidden_from_admin_list_and_client_menu(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'To Remove');
        $auth = $this->menuManagerAuth();
        $clientAuth = $this->authenticateMaxUser();
        $dishId = $fixture['dish']->id;

        $this->deleteJson("/api/food/admin/dishes/{$dishId}", [], $auth['headers'])
            ->assertNoContent();

        $this->getJson('/api/food/admin/dishes', $auth['headers'])
            ->assertOk()
            ->assertJsonCount(0, 'dishes');

        $this->getJson('/api/food/restaurants/'.$fixture['restaurant']->id.'/menu', $clientAuth['headers'])
            ->assertOk()
            ->assertJsonCount(0, 'menu.categories.0.dishes');
    }

    public function test_store_rejects_disallowed_extension(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $this->postMultipart('/api/food/admin/dishes', $this->validStorePayload(
            menuCategoryId: $fixture['category']->id,
            photo: DishPhotoTestImageFactory::disallowedGif(),
        ), $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_store_rejects_fake_mime(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $response = $this->postMultipart('/api/food/admin/dishes', $this->validStorePayload(
            menuCategoryId: $fixture['category']->id,
            photo: DishPhotoTestImageFactory::fakeMimePng(),
        ), $auth['headers']);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_store_rejects_photo_larger_than_25_megabytes(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $response = $this->postMultipart('/api/food/admin/dishes', $this->validStorePayload(
            menuCategoryId: $fixture['category']->id,
            photo: DishPhotoTestImageFactory::oversizedPng(),
        ), $auth['headers']);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);

        $this->assertStringContainsString(
            '25 МБ',
            (string) $response->json('errors.photo.0'),
        );
    }

    public function test_store_rejects_image_with_width_below_minimum(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $response = $this->postMultipart('/api/food/admin/dishes', $this->validStorePayload(
            menuCategoryId: $fixture['category']->id,
            photo: DishPhotoTestImageFactory::jpeg(799, 600),
        ), $auth['headers']);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);

        $this->assertStringContainsString(
            '800×600',
            (string) $response->json('errors.photo.0'),
        );
    }

    public function test_store_rejects_image_with_height_below_minimum(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $response = $this->postMultipart('/api/food/admin/dishes', $this->validStorePayload(
            menuCategoryId: $fixture['category']->id,
            photo: DishPhotoTestImageFactory::jpeg(800, 599),
        ), $auth['headers']);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);

        $this->assertStringContainsString(
            '800×600',
            (string) $response->json('errors.photo.0'),
        );
    }

    public function test_store_rejects_nonexistent_menu_category(): void
    {
        $auth = $this->menuManagerAuth();

        $this->postMultipart('/api/food/admin/dishes', $this->validStorePayload(
            menuCategoryId: 999_999,
        ), $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Категория меню не найдена.');
    }

    public function test_store_rejects_fractional_weight(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $response = $this->postMultipart('/api/food/admin/dishes', array_merge(
            $this->validStorePayload(menuCategoryId: $fixture['category']->id),
            ['weight' => '250.5'],
        ), $auth['headers']);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['weight']);

        $this->assertSame(
            'Вес должен быть целым числом.',
            (string) $response->json('errors.weight.0'),
        );
    }

    public function test_delete_soft_deletes_dish_and_preserves_image_file(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $createResponse = $this->postMultipart('/api/food/admin/dishes', $this->validStorePayload(
            menuCategoryId: $fixture['category']->id,
        ), $auth['headers'])->assertCreated();

        $dishId = (int) $createResponse->json('dish.id');
        $imagePath = (string) Dish::query()->findOrFail($dishId)->image_url;

        Storage::disk('public')->assertExists($imagePath);

        $this->deleteJson("/api/food/admin/dishes/{$dishId}", [], $auth['headers'])
            ->assertNoContent();

        $this->assertSoftDeleted('max_dishes', ['id' => $dishId]);
        Storage::disk('public')->assertExists($imagePath);

        $this->get('/api/food/dishes/'.$dishId.'/image')
            ->assertOk();
    }

    public function test_update_without_photo_preserves_existing_image_url(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $createResponse = $this->postMultipart('/api/food/admin/dishes', $this->validStorePayload(
            menuCategoryId: $fixture['category']->id,
            name: 'Keep Photo Dish',
        ), $auth['headers'])->assertCreated();

        $dishId = (int) $createResponse->json('dish.id');
        $originalImagePath = (string) Dish::query()->findOrFail($dishId)->image_url;

        $this->postMultipart("/api/food/admin/dishes/{$dishId}", [
            'name' => 'Updated Without Photo',
            'description' => 'New description',
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('dish.name', 'Updated Without Photo')
            ->assertJsonPath('dish.description', 'New description');

        $this->assertSame($originalImagePath, Dish::query()->findOrFail($dishId)->image_url);
        Storage::disk('public')->assertExists($originalImagePath);
    }

    public function test_update_with_photo_changes_public_image_url_version(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $createResponse = $this->postMultipart('/api/food/admin/dishes', $this->validStorePayload(
            menuCategoryId: $fixture['category']->id,
            name: 'Photo Version Dish',
        ), $auth['headers'])->assertCreated();

        $dishId = (int) $createResponse->json('dish.id');
        $originalPublicUrl = (string) $createResponse->json('dish.image_url');

        $updateResponse = $this->postMultipart("/api/food/admin/dishes/{$dishId}", [
            'name' => 'Photo Version Dish',
            'photo' => DishPhotoTestImageFactory::jpeg(800, 600, 'updated.jpg'),
        ], $auth['headers'])->assertOk();

        $updatedDish = Dish::query()->findOrFail($dishId);
        $updatedPublicUrl = (string) $updateResponse->json('dish.image_url');

        $this->assertNotSame($originalPublicUrl, $updatedPublicUrl);
        $this->assertSame($this->expectedDishImageUrl($dishId, $updatedDish->image_url), $updatedPublicUrl);
        Storage::disk('public')->assertExists((string) $updatedDish->image_url);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    private function postMultipart(string $uri, array $data, array $headers): TestResponse
    {
        return $this->post($uri, $data, [
            ...$headers,
            'Accept' => 'application/json',
        ]);
    }

    /**
     * @return array{user: MaxUser, token: string, headers: array<string, string>}
     */
    private function menuManagerAuth(): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_010,
                'first_name' => 'MenuManager',
            ])),
            FoodOrderAdminRole::MenuManager,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validStorePayload(
        int $menuCategoryId,
        string $name = 'New Dish',
        ?UploadedFile $photo = null,
    ): array {
        return [
            'name' => $name,
            'menu_category_id' => $menuCategoryId,
            'description' => 'Demo description',
            'weight' => '250',
            'weight_unit' => DishWeightUnit::Gram->value,
            'price' => '399.00',
            'vat_rate' => '10',
            'is_available' => '1',
            'photo' => $photo ?? DishPhotoTestImageFactory::jpeg(800, 600),
        ];
    }
}
