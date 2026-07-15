<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\DishWeightUnit;
use App\Enums\Food\FoodOrderAdminRole;
use App\Models\Dish;
use App\Models\MaxUser;
use App\Services\Max\LaravelMaxAdminBotTestSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->resetFoodDomainTables();
    }

    /** Админские эндпоинты блюд возвращают 401 без токена. */
    public function test_admin_dishes_endpoints_return_unauthorized_without_token(): void
    {
        $this->getJson('/api/food/admin/dishes')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    /** Админские эндпоинты блюд возвращают 403 без роли менеджера меню. */
    public function test_admin_dishes_endpoints_return_forbidden_without_menu_manager_role(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/admin/dishes', $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    /** Менеджер меню может отправить тестовое сообщение бота. */
    public function test_menu_manager_can_send_test_bot_message(): void
    {
        config([
            'max.bot_access_token' => 'secret-max-token-for-admin-dish-api-test',
            'max.bot_username' => 'food_test_bot',
            'max.order_notifications.chat_ids' => [555],
            'max.order_notifications.user_ids' => [],
            'max.rate_limit_retry_max' => 0,
            'max.rate_limit_retry_delay_ms' => 0,
        ]);

        Http::fake([
            'platform-api.max.ru/*' => Http::response(['message' => ['id' => 1]], 200),
        ]);

        $auth = $this->menuManagerAuth();

        $this->postJson('/api/food/admin/dishes/test-bot', [], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('message', 'Тестовое сообщение отправлено.')
            ->assertJsonPath('sent_count', 1)
            ->assertJsonPath('bot_username', 'food_test_bot');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://platform-api.max.ru/messages?chat_id=555'
                && $request['text'] === LaravelMaxAdminBotTestSender::TEST_MESSAGE_TEXT;
        });
    }

    /** Эндпоинт теста бота возвращает 403 без роли менеджера меню. */
    public function test_test_bot_endpoint_returns_forbidden_without_menu_manager_role(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->postJson('/api/food/admin/dishes/test-bot', [], $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    /** Эндпоинт теста бота возвращает 503, если получатели не заданы. */
    public function test_test_bot_endpoint_returns_service_unavailable_when_recipients_missing(): void
    {
        config([
            'max.bot_access_token' => 'secret-max-token-for-admin-dish-api-test',
            'max.bot_username' => 'food_test_bot',
            'max.order_notifications.chat_ids' => [],
            'max.order_notifications.user_ids' => [],
        ]);

        $auth = $this->menuManagerAuth();

        $this->postJson('/api/food/admin/dishes/test-bot', [], $auth['headers'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Получатели не настроены. Укажите MAX_REPORT_CHAT_IDS или MAX_REPORT_USER_IDS в .env.');
    }

    /** Менеджер меню может получить список категорий меню. */
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

    /** Менеджер меню может фильтровать блюда по ресторану и категории. */
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

    /** Менеджер меню может фильтровать блюда по имени. */
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

    /** CRUD блюд менеджером меню проходит по успешному сценарию. */
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

    /** Менеджер меню может обновить ставку НДС и сбросить её до exempt. */
    public function test_menu_manager_can_update_vat_rate_and_clear_to_exempt(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'Vat Dish');
        $auth = $this->menuManagerAuth();
        $dishId = $fixture['dish']->id;

        $this->postMultipart("/api/food/admin/dishes/{$dishId}", [
            'vat_rate' => '22',
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('dish.vat_rate', 22)
            ->assertJsonPath('dish.vat_rate_label', '22%');

        $this->postMultipart("/api/food/admin/dishes/{$dishId}", [
            'vat_rate' => '',
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('dish.vat_rate', null)
            ->assertJsonPath('dish.vat_rate_label', 'Не облагается НДС');

        $this->assertNull(Dish::query()->findOrFail($dishId)->vat_rate);
    }

    /** Удалённое блюдо скрыто из админ-списка и клиентского меню. */
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
            ->assertJsonCount(0, 'menu.categories');
    }

    /** Store отклоняет недопустимое расширение файла. */
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

    /** Store отклоняет поддельный MIME типа. */
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

    /** Store отклоняет фото больше 25 МБ. */
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

    /** Store отклоняет изображение с шириной ниже минимума. */
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

    /** Store отклоняет изображение с высотой ниже минимума. */
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

    /** Store отклоняет несуществующую категорию меню. */
    public function test_store_rejects_nonexistent_menu_category(): void
    {
        $auth = $this->menuManagerAuth();

        $this->postMultipart('/api/food/admin/dishes', $this->validStorePayload(
            menuCategoryId: 999_999,
        ), $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Категория меню не найдена.');
    }

    /** Store отклоняет дробный вес. */
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

    /** Delete мягко удаляет блюдо и сохраняет файл изображения. */
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

    /** Update без фото сохраняет существующий URL изображения. */
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

    /** Update с фото меняет версию публичного URL изображения. */
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
