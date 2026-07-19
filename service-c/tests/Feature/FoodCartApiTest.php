<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\CartStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Dish;
use App\Models\MaxUser;
use App\Models\MenuCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\Support\ResolvesDishImageUrl;
use Tests\TestCase;

class FoodCartApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;
    use ResolvesDishImageUrl;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** Показ корзины возвращает null, если черновой корзины нет. */
    public function test_cart_show_returns_null_when_no_draft_cart(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/cart', $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart', null)
            ->assertJsonPath('delivery_address', null);
    }

    /** Показ корзины подставляет сохранённый адрес профиля без черновика. */
    public function test_cart_show_returns_saved_delivery_address_when_no_draft_cart(): void
    {
        $auth = $this->authenticateMaxUser();
        $address = 'ул. Сохранённая, 5';

        $auth['user']->update(['delivery_address' => $address]);

        $this->getJson('/api/food/cart', $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart', null)
            ->assertJsonPath('delivery_address', $address);
    }

    /** Добавление позиции создаёт черновую корзину. */
    public function test_add_item_creates_draft_cart(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 199.50);

        $response = $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
        ], $auth['headers']);

        $dish = $fixture['dish'];
        $weightUnit = $dish->weight_unit;

        $response
            ->assertOk()
            ->assertJsonPath('cart.restaurant_id', $fixture['restaurant']->id)
            ->assertJsonPath('cart.status', CartStatus::Draft->value)
            ->assertJsonPath('cart.items.0.dish_id', $fixture['dish']->id)
            ->assertJsonPath('cart.items.0.quantity', 2)
            ->assertJsonPath('cart.items.0.unit_price', '199.50')
            ->assertJsonPath('cart.items.0.line_total', '399.00')
            ->assertJsonPath('cart.items.0.weight', (string) (int) round((float) $dish->weight))
            ->assertJsonPath('cart.items.0.weight_unit', $weightUnit->value)
            ->assertJsonPath('cart.items.0.weight_unit_label', $weightUnit->label())
            ->assertJsonPath('cart.items.0.image_url', $this->expectedDishImageUrlForModel($fixture['dish']))
            ->assertJsonPath('cart.items_total', '399.00')
            ->assertJsonPath('cart.delivery_cost', null)
            ->assertJsonPath('cart.total', '399.00')
            ->assertJsonPath('cart.delivery_applicable', false)
            ->assertJsonPath('cart.customer_category', null);

        $this->assertDatabaseHas('max_carts', [
            'max_user_id' => $auth['user']->max_user_id,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => CartStatus::Draft->value,
        ]);
    }

    /** Добавление позиций комбо возвращает и сохраняет метаданные комбо. */
    public function test_add_combo_items_returns_and_persists_combo_metadata(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Combo Place', 'Burger', 320);
        $sideCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Sides',
            'sort_order' => 2,
        ]);
        $sideDish = Dish::factory()->create([
            'menu_category_id' => $sideCategory->id,
            'name' => 'Fries',
            'price' => 180,
        ]);
        $comboRef = '550e8400-e29b-41d4-a716-446655440000';

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
            'combo_ref' => $comboRef,
            'combo_partner_dish_id' => $sideDish->id,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items.0.dish_id', $fixture['dish']->id)
            ->assertJsonPath('cart.items.0.quantity', 2)
            ->assertJsonPath('cart.items.0.combo_ref', $comboRef)
            ->assertJsonPath('cart.items.0.combo_partner_dish_id', $sideDish->id)
            ->assertJsonPath('cart.items.0.combo_partner_dish_name', 'Fries');

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $sideDish->id,
            'quantity' => 2,
            'combo_ref' => $comboRef,
            'combo_partner_dish_id' => $fixture['dish']->id,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items.0.combo_ref', $comboRef)
            ->assertJsonPath('cart.items.0.combo_partner_dish_id', $sideDish->id)
            ->assertJsonPath('cart.items.1.combo_ref', $comboRef)
            ->assertJsonPath('cart.items.1.combo_partner_dish_id', $fixture['dish']->id)
            ->assertJsonPath('cart.items.1.combo_partner_dish_name', 'Burger')
            ->assertJsonPath('cart.items_total', '1000.00');

        $this->assertDatabaseHas('max_cart_items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
            'combo_ref' => $comboRef,
            'combo_partner_dish_id' => $sideDish->id,
        ]);
        $this->assertDatabaseHas('max_cart_items', [
            'dish_id' => $sideDish->id,
            'quantity' => 2,
            'combo_ref' => $comboRef,
            'combo_partner_dish_id' => $fixture['dish']->id,
        ]);
    }

    /** Добавление позиции валидирует payload. */
    public function test_add_item_validates_payload(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->postJson('/api/food/cart/items', [], $auth['headers'])
            ->assertUnprocessable();
    }

    /** Добавление позиции отклоняет недоступное блюдо. */
    public function test_add_item_rejects_unavailable_dish(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $fixture['dish']->update(['is_available' => false]);

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Dish is not available.');
    }

    /** Добавление позиции отклоняет блюдо другого ресторана, если корзина уже есть. */
    public function test_add_item_rejects_dish_from_another_restaurant_when_cart_exists(): void
    {
        $auth = $this->authenticateMaxUser();
        $first = FoodTestDataBuilder::createRestaurantWithDish('First', 'Soup', 100);
        $second = FoodTestDataBuilder::createRestaurantWithDish('Second', 'Salad', 120);

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $first['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])->assertOk();

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $second['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Cart already contains items from another restaurant. Clear the cart before adding dishes from a different restaurant.');
    }

    /** Обновляет количество позиции корзины. */
    public function test_update_cart_item_quantity(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);

        $addResponse = $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers']);

        $cartItemId = (int) $addResponse->json('cart.items.0.id');

        $this->patchJson('/api/food/cart/items/'.$cartItemId, [
            'quantity' => 3,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items.0.quantity', 3)
            ->assertJsonPath('cart.items_total', '300.00')
            ->assertJsonPath('cart.delivery_cost', null)
            ->assertJsonPath('cart.total', '300.00');
    }

    /** Удаление последней позиции возвращает null-корзину. */
    public function test_delete_cart_item_returns_null_cart_when_last_item_removed(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();

        $addResponse = $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers']);

        $cartItemId = (int) $addResponse->json('cart.items.0.id');

        $this->deleteJson('/api/food/cart/items/'.$cartItemId, [], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart', null);

        $this->assertDatabaseMissing('max_carts', [
            'max_user_id' => $auth['user']->max_user_id,
            'status' => CartStatus::Draft->value,
        ]);
    }

    /** Очистка корзины удаляет все позиции и черновую корзину. */
    public function test_clear_cart_removes_all_items_and_draft_cart(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
        ], $auth['headers']);

        $this->deleteJson('/api/food/cart', [], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart', null);

        $this->assertDatabaseMissing('max_carts', [
            'max_user_id' => $auth['user']->max_user_id,
            'status' => CartStatus::Draft->value,
        ]);

        $this->getJson('/api/food/cart', $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart', null);
    }

    /** Очистка пустой корзины идемпотентна. */
    public function test_clear_cart_is_idempotent_when_cart_is_empty(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->deleteJson('/api/food/cart', [], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart', null);
    }

    /** Операции с позициями отклоняют чужую позицию корзины. */
    public function test_cart_item_operations_reject_foreign_cart_item(): void
    {
        $auth = $this->authenticateMaxUser();
        $otherUser = $this->authenticateMaxUser(
            MaxUser::query()->create([
                'max_user_id' => 88_002,
                'first_name' => 'Other',
            ]),
        );

        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $cart = Cart::query()->create([
            'max_user_id' => $otherUser['user']->max_user_id,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => CartStatus::Draft,
        ]);

        $cartItem = CartItem::query()->create([
            'cart_id' => $cart->id,
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ]);

        $this->patchJson('/api/food/cart/items/'.$cartItem->id, [
            'quantity' => 2,
        ], $auth['headers'])
            ->assertNotFound();

        $this->deleteJson('/api/food/cart/items/'.$cartItem->id, [], $auth['headers'])
            ->assertNotFound();
    }

    /** Добавление позиции увеличивает количество существующего блюда. */
    public function test_add_item_increments_existing_dish_quantity(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 50);

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])->assertOk();

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items.0.quantity', 3)
            ->assertJsonPath('cart.items_total', '150.00')
            ->assertJsonPath('cart.delivery_cost', null)
            ->assertJsonPath('cart.total', '150.00');
    }

    /** Корзина включает стоимость доставки для пользователя с категорией. */
    public function test_cart_includes_delivery_cost_for_user_with_category(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            price: 199.50,
            tiers: [
                ['min_items_total' => 1000.00, 'delivery_cost' => 0.00],
                ['min_items_total' => 0.00, 'delivery_cost' => 150.00],
            ],
        );

        $auth = $this->authenticateMaxUser(
            FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category']),
        );

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items_total', '399.00')
            ->assertJsonPath('cart.delivery_cost', '150.00')
            ->assertJsonPath('cart.total', '549.00')
            ->assertJsonPath('cart.delivery_applicable', true)
            ->assertJsonPath('cart.customer_category.id', $fixture['customer_category']->id)
            ->assertJsonPath('cart.customer_category.name', 'Standard');
    }

    /** Корзина применяет тариф доставки при сумме позиций 999. */
    public function test_cart_applies_delivery_tier_at_999_items_total(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            price: 999.00,
        );

        $auth = $this->authenticateMaxUser(
            FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category']),
        );

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items_total', '999.00')
            ->assertJsonPath('cart.delivery_cost', '200.00')
            ->assertJsonPath('cart.total', '1199.00')
            ->assertJsonPath('cart.delivery_applicable', true)
            ->assertJsonPath('cart.next_tier_min_total', '1000.00')
            ->assertJsonPath('cart.next_tier_delivery_cost', '0.00')
            ->assertJsonPath('cart.amount_to_next_tier', '1.00');
    }

    /** Корзина не показывает подсказку тарифа на лучшем тарифе. */
    public function test_cart_omits_delivery_tier_hint_on_best_tier(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            price: 500.00,
        );

        $auth = $this->authenticateMaxUser(
            FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category']),
        );

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items_total', '1000.00')
            ->assertJsonPath('cart.delivery_cost', '0.00')
            ->assertJsonPath('cart.next_tier_min_total', null)
            ->assertJsonPath('cart.next_tier_delivery_cost', null)
            ->assertJsonPath('cart.amount_to_next_tier', null);
    }

    /** Корзина не показывает подсказку тарифа без категории. */
    public function test_cart_omits_delivery_tier_hint_without_category(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(price: 999.00);

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.delivery_applicable', false)
            ->assertJsonPath('cart.next_tier_min_total', null)
            ->assertJsonPath('cart.next_tier_delivery_cost', null)
            ->assertJsonPath('cart.amount_to_next_tier', null);
    }

    /** Корзина применяет бесплатную доставку при сумме позиций 1000. */
    public function test_cart_applies_free_delivery_tier_at_1000_items_total(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            price: 500.00,
        );

        $auth = $this->authenticateMaxUser(
            FoodTestDataBuilder::createMaxUserWithCategory($fixture['customer_category']),
        );

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items_total', '1000.00')
            ->assertJsonPath('cart.delivery_cost', '0.00')
            ->assertJsonPath('cart.total', '1000.00')
            ->assertJsonPath('cart.delivery_applicable', true);
    }

    /** Корзина без категории исключает доставку из итога. */
    public function test_cart_without_category_excludes_delivery_from_total(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(price: 250.00);

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.items_total', '250.00')
            ->assertJsonPath('cart.delivery_applicable', false)
            ->assertJsonPath('cart.delivery_cost', null)
            ->assertJsonPath('cart.total', '250.00')
            ->assertJsonPath('cart.customer_category', null);
    }

    /** PATCH обновляет адрес доставки корзины. */
    public function test_patch_cart_delivery_address(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])->assertOk();

        $address = 'ул. Примерная, 1';

        $this->patchJson('/api/food/cart', [
            'delivery_address' => $address,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.delivery_address', $address);

        $this->assertDatabaseHas('max_carts', [
            'max_user_id' => $auth['user']->max_user_id,
            'status' => CartStatus::Draft->value,
            'delivery_address' => $address,
        ]);

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => $auth['user']->max_user_id,
            'delivery_address' => $address,
        ]);
    }

    /** Новая корзина подставляет адрес доставки из профиля MAX. */
    public function test_new_cart_prefills_delivery_address_from_max_user(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);
        $address = 'ул. Сохранённая, 5';

        $auth['user']->update(['delivery_address' => $address]);

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart.delivery_address', $address);

        $this->assertDatabaseHas('max_carts', [
            'max_user_id' => $auth['user']->max_user_id,
            'status' => CartStatus::Draft->value,
            'delivery_address' => $address,
        ]);
    }

    /** PATCH адреса доставки валидирует payload. */
    public function test_patch_cart_delivery_address_validates_payload(): void
    {
        $auth = $this->authenticateMaxUser();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();

        $this->postJson('/api/food/cart/items', [
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ], $auth['headers'])->assertOk();

        $this->patchJson('/api/food/cart', [], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Укажите адрес доставки.');

        $this->patchJson('/api/food/cart', [
            'delivery_address' => '',
        ], $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Укажите адрес доставки.');
    }

    /** PATCH адреса доставки без корзины сохраняет адрес в профиле. */
    public function test_patch_cart_delivery_address_persists_to_profile_without_draft_cart(): void
    {
        $auth = $this->authenticateMaxUser();
        $address = 'ул. Примерная, 1';

        $this->patchJson('/api/food/cart', [
            'delivery_address' => $address,
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('cart', null)
            ->assertJsonPath('delivery_address', $address);

        $this->assertDatabaseHas('max_users', [
            'max_user_id' => $auth['user']->max_user_id,
            'delivery_address' => $address,
        ]);
    }

    /**
     * Чат привязан к заказу (orders/{id}/messages), не к черновой корзине.
     */
    public function test_cart_messages_routes_are_not_registered(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/cart/messages', $auth['headers'])
            ->assertNotFound();

        $this->postJson('/api/food/cart/messages', [
            'body' => 'test',
        ], $auth['headers'])
            ->assertNotFound();
    }
}
