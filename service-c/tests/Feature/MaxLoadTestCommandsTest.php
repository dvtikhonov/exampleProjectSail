<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\Cart\CartStatus;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Models\Food\Cart;
use App\Models\Food\FoodOrder;
use App\Models\Max\MaxUser;
use App\Support\Max\MaxLoadTestUserIds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class MaxLoadTestCommandsTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private string $tokensFile;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
        $this->tokensFile = storage_path('app/testing-load-test-tokens.json');

        if (is_file($this->tokensFile)) {
            unlink($this->tokensFile);
        }
    }

    /** Очистка артефактов после теста. */
    protected function tearDown(): void
    {
        if (is_file($this->tokensFile)) {
            unlink($this->tokensFile);
        }

        parent::tearDown();
    }

    /** tokens создаёт пользователей, токены и JSON-файл. */
    public function test_tokens_command_creates_users_and_writes_json(): void
    {
        $exitCode = Artisan::call('max:load-test:tokens', [
            'count' => 2,
            '--output' => $this->tokensFile,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->tokensFile);

        $payload = json_decode((string) file_get_contents($this->tokensFile), true);
        $this->assertIsArray($payload);
        $this->assertCount(2, $payload);

        $this->assertSame(MaxLoadTestUserIds::BASE_ID, $payload[0]['max_user_id']);
        $this->assertSame(MaxLoadTestUserIds::BASE_ID + 1, $payload[1]['max_user_id']);
        $this->assertNotEmpty($payload[0]['token']);
        $this->assertNotEmpty($payload[1]['token']);

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => MaxLoadTestUserIds::BASE_ID,
            'first_name' => 'LoadTest1',
        ]);
        $this->assertDatabaseHas('max_users', [
            'max_user_id' => MaxLoadTestUserIds::BASE_ID + 1,
            'first_name' => 'LoadTest2',
        ]);

        $token = PersonalAccessToken::findToken($payload[0]['token']);
        $this->assertNotNull($token);
        $this->assertTrue($token->can('max-miniapp'));
        $this->assertSame(MaxLoadTestUserIds::BASE_ID, (int) $token->tokenable_id);
    }

    /** Повторный tokens перевыпускает токен без дублирования пользователей. */
    public function test_tokens_command_reissues_token_for_existing_user(): void
    {
        Artisan::call('max:load-test:tokens', [
            'count' => 1,
            '--output' => $this->tokensFile,
        ]);

        $firstPayload = json_decode((string) file_get_contents($this->tokensFile), true);
        $firstToken = $firstPayload[0]['token'];

        $exitCode = Artisan::call('max:load-test:tokens', [
            'count' => 1,
            '--output' => $this->tokensFile,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, MaxUser::query()->whereKey(MaxLoadTestUserIds::BASE_ID)->count());

        $secondPayload = json_decode((string) file_get_contents($this->tokensFile), true);
        $this->assertNotSame($firstToken, $secondPayload[0]['token']);
        $this->assertNull(PersonalAccessToken::findToken($firstToken));
        $this->assertNotNull(PersonalAccessToken::findToken($secondPayload[0]['token']));
    }

    /** prepare-menu включает is_available у блюд активных ресторанов. */
    public function test_prepare_menu_command_enables_dishes_for_active_restaurants(): void
    {
        $active = FoodTestDataBuilder::createRestaurantWithDish('Active R', 'Active Dish');
        $active['dish']->update(['is_available' => false]);

        $inactive = FoodTestDataBuilder::createRestaurantWithDish('Inactive R', 'Inactive Dish');
        $inactive['restaurant']->update(['is_active' => false]);
        $inactive['dish']->update(['is_available' => false]);

        $exitCode = Artisan::call('max:load-test:prepare-menu');

        $this->assertSame(0, $exitCode);
        $this->assertTrue($active['dish']->fresh()->is_available);
        $this->assertFalse($inactive['dish']->fresh()->is_available);
        $this->assertStringContainsString('Включено блюд: 1', Artisan::output());
    }

    /** cleanup удаляет заказы и корзины load-test пользователей. */
    public function test_cleanup_command_deletes_orders_and_carts(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $maxUserId = MaxLoadTestUserIds::BASE_ID;

        MaxUser::query()->create([
            'max_user_id' => $maxUserId,
            'first_name' => 'LoadTest1',
        ]);

        $cart = Cart::query()->create([
            'max_user_id' => $maxUserId,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => CartStatus::Submitted,
            'delivery_address' => 'Load test street',
        ]);

        FoodOrder::query()->create([
            'cart_id' => $cart->id,
            'max_user_id' => $maxUserId,
            'is_manual' => false,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => OrderStatus::Confirmed,
            'address_review_status' => OrderReviewStatus::Approved,
            'composition_review_status' => OrderReviewStatus::Approved,
            'payment_review_status' => OrderReviewStatus::Approved,
            'total' => 100,
            'items_total' => 100,
            'delivery_cost' => 0,
            'delivery_address' => 'Load test street',
            'items_snapshot' => [],
        ]);

        $otherUser = MaxUser::query()->create([
            'max_user_id' => 1001,
            'first_name' => 'Demo',
        ]);
        $otherCart = Cart::query()->create([
            'max_user_id' => $otherUser->max_user_id,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => CartStatus::Draft,
            'delivery_address' => 'Keep me',
        ]);

        $exitCode = Artisan::call('max:load-test:cleanup', ['count' => 1]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseMissing('max_food_orders', ['max_user_id' => $maxUserId]);
        $this->assertDatabaseMissing('max_carts', ['max_user_id' => $maxUserId]);
        $this->assertDatabaseHas('max_users', ['max_user_id' => $maxUserId]);
        $this->assertDatabaseHas('max_carts', ['id' => $otherCart->id]);
    }

    /** Команды отклоняются вне local/testing. */
    public function test_commands_fail_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        $tokensExit = Artisan::call('max:load-test:tokens', [
            'count' => 1,
            '--output' => $this->tokensFile,
        ]);
        $prepareExit = Artisan::call('max:load-test:prepare-menu');
        $cleanupExit = Artisan::call('max:load-test:cleanup', ['count' => 1]);

        $this->assertSame(1, $tokensExit);
        $this->assertSame(1, $prepareExit);
        $this->assertSame(1, $cleanupExit);
        $this->assertStringContainsString('local или testing', Artisan::output());
        $this->assertFileDoesNotExist($this->tokensFile);
    }

    /** tokens отклоняет некорректный count. */
    public function test_tokens_command_rejects_invalid_count(): void
    {
        $exitCode = Artisan::call('max:load-test:tokens', [
            'count' => 0,
            '--output' => $this->tokensFile,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertFileDoesNotExist($this->tokensFile);
    }
}
