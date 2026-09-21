<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Food\Cart\CartDraftContext;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Cart\CartStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Food\Cart;
use App\Models\Food\CartItem;
use App\Models\Max\MaxUser;
use App\Services\Food\Cart\CartItemMutationCoordinator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class CartItemMutationCoordinatorTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** updateQuantity отклоняет позицию чужого владельца. */
    public function test_update_quantity_rejects_wrong_owner(): void
    {
        $owner = MaxUser::query()->create([
            'max_user_id' => 13_001,
            'first_name' => 'Owner',
        ]);
        $stranger = MaxUser::query()->create([
            'max_user_id' => 13_002,
            'first_name' => 'Stranger',
        ]);
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);
        $cartItem = $this->createUserDraftItem($owner, $fixture['restaurant']->id, $fixture['dish']->id);

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('Позиция корзины не найдена.');

        app(CartItemMutationCoordinator::class)->performUpdateQuantity(
            CartDraftContext::user($this->identity($stranger)),
            $cartItem->id,
            2,
        );
    }

    /** updateQuantity отклоняет ручную позицию другого менеджера. */
    public function test_update_quantity_rejects_wrong_manager(): void
    {
        $customer = MaxUser::query()->create([
            'max_user_id' => 13_011,
            'first_name' => 'Customer',
        ]);
        $manager = MaxUser::query()->create([
            'max_user_id' => 13_012,
            'first_name' => 'Manager',
        ]);
        $otherManager = MaxUser::query()->create([
            'max_user_id' => 13_013,
            'first_name' => 'OtherManager',
        ]);
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);
        $cartItem = $this->createManualDraftItem(
            $customer,
            $manager,
            $fixture['restaurant']->id,
            $fixture['dish']->id,
        );

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('Позиция корзины не найдена.');

        app(CartItemMutationCoordinator::class)->performUpdateQuantity(
            CartDraftContext::manual($this->identity($customer), $this->identity($otherManager)),
            $cartItem->id,
            2,
        );
    }

    /** updateQuantity в user-контексте отклоняет позицию manual-корзины того же владельца. */
    public function test_update_quantity_user_context_rejects_manual_draft_item(): void
    {
        $customer = MaxUser::query()->create([
            'max_user_id' => 13_041,
            'first_name' => 'Customer',
        ]);
        $manager = MaxUser::query()->create([
            'max_user_id' => 13_042,
            'first_name' => 'Manager',
        ]);
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);
        $cartItem = $this->createManualDraftItem(
            $customer,
            $manager,
            $fixture['restaurant']->id,
            $fixture['dish']->id,
        );

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('Позиция корзины не найдена.');

        app(CartItemMutationCoordinator::class)->performUpdateQuantity(
            CartDraftContext::user($this->identity($customer)),
            $cartItem->id,
            2,
        );
    }

    /** removeItem в user-контексте отклоняет позицию manual-корзины того же владельца. */
    public function test_remove_item_user_context_rejects_manual_draft_item(): void
    {
        $customer = MaxUser::query()->create([
            'max_user_id' => 13_051,
            'first_name' => 'Customer',
        ]);
        $manager = MaxUser::query()->create([
            'max_user_id' => 13_052,
            'first_name' => 'Manager',
        ]);
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);
        $cartItem = $this->createManualDraftItem(
            $customer,
            $manager,
            $fixture['restaurant']->id,
            $fixture['dish']->id,
        );

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('Позиция корзины не найдена.');

        app(CartItemMutationCoordinator::class)->performRemoveItem(
            CartDraftContext::user($this->identity($customer)),
            $cartItem->id,
        );
    }

    /** updateQuantity отклоняет позицию не-черновой корзины. */
    public function test_update_quantity_rejects_non_draft_cart(): void
    {
        $owner = MaxUser::query()->create([
            'max_user_id' => 13_021,
            'first_name' => 'Owner',
        ]);
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);
        $cart = Cart::query()->create([
            'max_user_id' => $owner->max_user_id,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => CartStatus::Submitted,
        ]);
        $cartItem = CartItem::query()->create([
            'cart_id' => $cart->id,
            'dish_id' => $fixture['dish']->id,
            'quantity' => 1,
        ]);

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('Корзина больше недоступна для редактирования.');

        app(CartItemMutationCoordinator::class)->performUpdateQuantity(
            CartDraftContext::user($this->identity($owner)),
            $cartItem->id,
            2,
        );
    }

    /** removeItem удаляет последнюю позицию и возвращает null. */
    public function test_remove_item_returns_null_when_last_item_removed(): void
    {
        $owner = MaxUser::query()->create([
            'max_user_id' => 13_031,
            'first_name' => 'Owner',
        ]);
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);
        $cartItem = $this->createUserDraftItem($owner, $fixture['restaurant']->id, $fixture['dish']->id);
        $context = CartDraftContext::user($this->identity($owner));
        $coordinator = app(CartItemMutationCoordinator::class);

        $result = $coordinator->performRemoveItem($context, $cartItem->id);

        $this->assertNull($result);
        $this->assertNull($coordinator->resolveDraft($context));
        $this->assertDatabaseMissing('max_carts', [
            'max_user_id' => $owner->max_user_id,
            'status' => CartStatus::Draft->value,
        ]);
    }

    private function createUserDraftItem(MaxUser $owner, int $restaurantId, int $dishId): CartItem
    {
        $cart = Cart::query()->create([
            'max_user_id' => $owner->max_user_id,
            'restaurant_id' => $restaurantId,
            'status' => CartStatus::Draft,
        ]);

        return CartItem::query()->create([
            'cart_id' => $cart->id,
            'dish_id' => $dishId,
            'quantity' => 1,
        ]);
    }

    private function createManualDraftItem(
        MaxUser $customer,
        MaxUser $manager,
        int $restaurantId,
        int $dishId,
    ): CartItem {
        $cart = Cart::query()->create([
            'max_user_id' => $customer->max_user_id,
            'created_by_max_user_id' => $manager->max_user_id,
            'restaurant_id' => $restaurantId,
            'status' => CartStatus::Draft,
        ]);

        return CartItem::query()->create([
            'cart_id' => $cart->id,
            'dish_id' => $dishId,
            'quantity' => 1,
        ]);
    }

    private function identity(MaxUser $user): MaxUserIdentity
    {
        return new MaxUserIdentity(
            maxUserId: (int) $user->max_user_id,
            adminRoles: [],
        );
    }
}
