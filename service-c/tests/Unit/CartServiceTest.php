<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Cart\CartStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Food\Cart;
use App\Models\Food\Dish;
use App\Models\Food\MenuCategory;
use App\Models\Max\MaxUser;
use App\Services\Food\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** getDraftCart возвращает null, если корзины нет. */
    public function test_get_draft_cart_returns_null_when_missing(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 11_001,
            'first_name' => 'Cart',
        ]);

        $cart = app(CartService::class)->getDraftCart($this->identity($maxUser));

        $this->assertNull($cart);
    }

    /** addItem выбрасывает исключение, если блюдо не найдено. */
    public function test_add_item_throws_when_dish_not_found(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 11_002,
            'first_name' => 'Cart',
        ]);

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('Блюдо не найдено.');

        app(CartService::class)->addItem($this->identity($maxUser), 99_999, 1);
    }

    /** addItem отклоняет недоступное блюдо. */
    public function test_add_item_rejects_unavailable_dish(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 11_006,
            'first_name' => 'Cart',
        ]);
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);
        $fixture['dish']->update(['is_available' => false]);

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('Блюдо недоступно.');

        app(CartService::class)->addItem($this->identity($maxUser), $fixture['dish']->id, 1);
    }

    /** addItem создаёт корзину с ожидаемым итогом. */
    public function test_add_item_creates_cart_with_expected_total(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 11_003,
            'first_name' => 'Cart',
        ]);

        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 125.25);

        $cart = app(CartService::class)->addItem($this->identity($maxUser), $fixture['dish']->id, 2);

        $this->assertSame(CartStatus::Draft->value, $cart->status);
        $this->assertSame('250.50', $cart->total);
        $this->assertCount(1, $cart->items);
    }

    /** addItem подставляет адрес доставки из профиля MAX. */
    public function test_add_item_prefills_cart_delivery_address_from_max_user(): void
    {
        $address = 'ул. Домашняя, 3';
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 11_004,
            'first_name' => 'Cart',
            'delivery_address' => $address,
        ]);

        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);

        $cart = app(CartService::class)->addItem($this->identity($maxUser), $fixture['dish']->id, 1);

        $this->assertSame($address, $cart->deliveryAddress);
    }

    /** getDraftCart подставляет адрес из профиля, если в корзине адрес пустой. */
    public function test_get_draft_cart_falls_back_to_user_delivery_address_when_cart_address_empty(): void
    {
        $address = 'ул. Профильная, 7';
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 11_005,
            'first_name' => 'Cart',
            'delivery_address' => $address,
        ]);

        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);

        $cart = app(CartService::class)->addItem($this->identity($maxUser), $fixture['dish']->id, 1);

        Cart::query()->whereKey($cart->id)->update(['delivery_address' => null]);

        $reloaded = app(CartService::class)->getDraftCart($this->identity($maxUser->fresh()));

        $this->assertNotNull($reloaded);
        $this->assertSame($address, $reloaded->deliveryAddress);
    }

    /** addItem увеличивает количество существующей комбо-позиции. */
    public function test_add_combo_item_upserts_quantity(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 11_007,
            'first_name' => 'Cart',
        ]);
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Combo Unit', 'Burger', 320);
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
        $cartService = app(CartService::class);
        $identity = $this->identity($maxUser);

        $cartService->addItem(
            $identity,
            $fixture['dish']->id,
            2,
            $comboRef,
            $sideDish->id,
        );
        $cart = $cartService->addItem(
            $identity,
            $fixture['dish']->id,
            1,
            $comboRef,
            $sideDish->id,
        );

        $this->assertCount(1, $cart->items);
        $this->assertSame(3, $cart->items[0]->quantity);
        $this->assertSame($comboRef, $cart->items[0]->comboRef);
    }

    /** updateItemQuantity обновляет итог корзины. */
    public function test_update_item_quantity_updates_cart_total(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 11_008,
            'first_name' => 'Cart',
        ]);
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);
        $cartService = app(CartService::class);
        $identity = $this->identity($maxUser);

        $cart = $cartService->addItem($identity, $fixture['dish']->id, 1);
        $cartItemId = $cart->items[0]->id;

        $updated = $cartService->updateItemQuantity($identity, $cartItemId, 4);

        $this->assertSame('400.00', $updated->total);
        $this->assertSame(4, $updated->items[0]->quantity);
    }

    /** removeItem удаляет позицию и возвращает null при пустой корзине. */
    public function test_remove_item_returns_null_when_cart_becomes_empty(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 11_009,
            'first_name' => 'Cart',
        ]);
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);
        $cartService = app(CartService::class);
        $identity = $this->identity($maxUser);

        $cart = $cartService->addItem($identity, $fixture['dish']->id, 1);
        $cartItemId = $cart->items[0]->id;

        $result = $cartService->removeItem($identity, $cartItemId);

        $this->assertNull($result);
        $this->assertNull($cartService->getDraftCart($identity));
    }

    /** clear удаляет черновую корзину вместе с позициями. */
    public function test_clear_removes_draft_cart_with_items(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 11_010,
            'first_name' => 'Cart',
        ]);

        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);
        $cartService = app(CartService::class);
        $identity = $this->identity($maxUser);

        $cartService->addItem($identity, $fixture['dish']->id, 2);
        $cartService->clear($identity);

        $this->assertNull($cartService->getDraftCart($identity));
    }

    /** Преобразует MaxUser в доменную идентичность для CartService. */
    private function identity(MaxUser $user): MaxUserIdentity
    {
        return new MaxUserIdentity(
            maxUserId: (int) $user->max_user_id,
            adminRoles: [],
        );
    }
}
