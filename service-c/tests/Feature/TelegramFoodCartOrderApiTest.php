<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\Cart\CartStatus;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\MessengerChannel;
use App\Models\Food\Cart;
use App\Models\Food\FoodOrder;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Клиентский food API под токеном telegram-miniapp и изоляция корзин при коллизии external id.
 */
class TelegramFoodCartOrderApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** Токен telegram-miniapp может добавить позицию в корзину. */
    public function test_telegram_miniapp_token_can_manage_cart(): void
    {
        $auth = $this->authenticateTelegramUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 199.50);

        $response = $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
        ], $auth['headers']);

        $response
            ->assertOk()
            ->assertJsonPath('cart.status', CartStatus::Draft->value)
            ->assertJsonPath('cart.items.0.dish_id', $fixture['dish']->id)
            ->assertJsonPath('cart.items.0.quantity', 2)
            ->assertJsonPath('cart.total', '399.00');

        $this->assertDatabaseHas('max_carts', [
            'max_user_id' => $auth['user']->max_user_id,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => CartStatus::Draft->value,
        ]);
        $this->assertSame(
            MessengerChannel::Telegram->toInternalMaxUserId(77_001),
            $auth['user']->max_user_id,
        );
    }

    /** Токен telegram-miniapp может оформить заказ. */
    public function test_telegram_miniapp_token_can_submit_order(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Tg Order Place',
            'Tg Steak',
            700,
        );
        $auth = $this->authenticateTelegramUser(
            FoodTestDataBuilder::assignCustomerCategory(
                MaxUser::query()->create([
                    'max_user_id' => MessengerChannel::Telegram->toInternalMaxUserId(88_042),
                    'messenger' => MessengerChannel::Telegram,
                    'external_user_id' => 88_042,
                    'first_name' => 'TgOrderer',
                ]),
                $fixture['customer_category'],
            ),
            88_042,
        );
        $address = 'ул. Telegram, 1';

        $this->addItemToCart($auth, $fixture['dish']->id, 2);
        $this->setCartDeliveryAddress($auth, $address);

        $response = $this->postJson('/api/food/orders/submit', [], $auth['headers']);

        $response
            ->assertCreated()
            ->assertJsonPath('order.status', OrderStatus::PendingReview->value)
            ->assertJsonPath('order.restaurant_id', $fixture['restaurant']->id)
            ->assertJsonPath('order.delivery_address', $address)
            ->assertJsonPath('order.items_snapshot.0.dish_name', 'Tg Steak');

        $this->assertDatabaseHas('max_food_orders', [
            'max_user_id' => $auth['user']->max_user_id,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => OrderStatus::PendingReview->value,
            'delivery_address' => $address,
        ]);
        $this->assertDatabaseHas('max_carts', [
            'max_user_id' => $auth['user']->max_user_id,
            'status' => CartStatus::Submitted->value,
        ]);
        $this->assertSame(1, FoodOrder::query()->count());
    }

    /** Одинаковый external id у MAX и Telegram — две независимые корзины. */
    public function test_same_external_id_yields_separate_carts_for_max_and_telegram(): void
    {
        $externalId = 67_890;
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Twin Place', 'Twin Dish', 150);

        $maxAuth = $this->authenticateMaxUser(
            MaxUser::query()->create([
                'max_user_id' => $externalId,
                'messenger' => MessengerChannel::Max,
                'external_user_id' => $externalId,
                'first_name' => 'MaxTwin',
            ]),
        );
        $tgAuth = $this->authenticateTelegramUser(
            MaxUser::query()->create([
                'max_user_id' => MessengerChannel::Telegram->toInternalMaxUserId($externalId),
                'messenger' => MessengerChannel::Telegram,
                'external_user_id' => $externalId,
                'first_name' => 'TgTwin',
            ]),
            $externalId,
        );

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $maxAuth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items.0.quantity', 1);

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 3,
        ], $tgAuth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items.0.quantity', 3);

        $this->getJson('/api/food/cart', $maxAuth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items.0.quantity', 1)
            ->assertJsonPath('cart.total', '150.00');

        $this->getJson('/api/food/cart', $tgAuth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items.0.quantity', 3)
            ->assertJsonPath('cart.total', '450.00');

        $this->assertDatabaseHas('max_carts', [
            'max_user_id' => $externalId,
            'status' => CartStatus::Draft->value,
        ]);
        $this->assertDatabaseHas('max_carts', [
            'max_user_id' => MessengerChannel::Telegram->toInternalMaxUserId($externalId),
            'status' => CartStatus::Draft->value,
        ]);
        $this->assertSame(2, Cart::query()->count());
    }

    /**
     * @param  array{headers: array<string, string>, user: MaxUser}  $auth
     */
    private function addItemToCart(array $auth, int $dishId, int $quantity): TestResponse
    {
        return $this->postJson('/api/food/cart/items', [
            'dish_id' => $dishId,
            'quantity' => $quantity,
        ], $auth['headers']);
    }

    /**
     * @param  array{headers: array<string, string>}  $auth
     */
    private function setCartDeliveryAddress(array $auth, string $address): void
    {
        $this->patchJson('/api/food/cart', [
            'delivery_address' => $address,
        ], $auth['headers'])->assertOk();
    }
}
