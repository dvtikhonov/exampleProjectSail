<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Cart\CartStatus;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Food\Cart;
use App\Models\Max\MaxUser;
use App\Services\Food\ManualOrder\ManualOrderCartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class ManualOrderCartServiceTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** getDraftCart возвращает null, если ручной корзины нет. */
    public function test_get_draft_cart_returns_null_when_missing(): void
    {
        [$customer, $manager] = $this->createCustomerAndManager();

        $cart = app(ManualOrderCartService::class)->getDraftCart(
            $this->customerIdentity($customer),
            $this->managerIdentity($manager),
        );

        $this->assertNull($cart);
    }

    /** addItem создаёт ручную корзину с created_by_max_user_id менеджера. */
    public function test_add_item_creates_manual_cart_with_manager_id(): void
    {
        [$customer, $manager] = $this->createCustomerAndManager();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 150);

        $cart = app(ManualOrderCartService::class)->addItem(
            $this->customerIdentity($customer),
            $this->managerIdentity($manager),
            $fixture['dish']->id,
            2,
        );

        $this->assertSame(CartStatus::Draft->value, $cart->status);
        $this->assertSame('300.00', $cart->total);
        $this->assertCount(1, $cart->items);

        $this->assertDatabaseHas('max_carts', [
            'max_user_id' => $customer->max_user_id,
            'created_by_max_user_id' => $manager->max_user_id,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => CartStatus::Draft->value,
        ]);
    }

    /** addItem в ручном режиме не блокирует недоступное блюдо. */
    public function test_add_item_allows_unavailable_dish(): void
    {
        [$customer, $manager] = $this->createCustomerAndManager();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 120);
        $fixture['dish']->update(['is_available' => false]);

        $cart = app(ManualOrderCartService::class)->addItem(
            $this->customerIdentity($customer),
            $this->managerIdentity($manager),
            $fixture['dish']->id,
            1,
        );

        $this->assertCount(1, $cart->items);
        $this->assertSame('120.00', $cart->total);
    }

    /** updateItemQuantity обновляет количество в ручной корзине. */
    public function test_update_item_quantity_updates_cart_total(): void
    {
        [$customer, $manager] = $this->createCustomerAndManager();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);
        $service = app(ManualOrderCartService::class);
        $customerIdentity = $this->customerIdentity($customer);
        $managerIdentity = $this->managerIdentity($manager);

        $cart = $service->addItem($customerIdentity, $managerIdentity, $fixture['dish']->id, 1);
        $cartItemId = $cart->items[0]->id;

        $updated = $service->updateItemQuantity($customerIdentity, $managerIdentity, $cartItemId, 3);

        $this->assertSame('300.00', $updated->total);
        $this->assertSame(3, $updated->items[0]->quantity);
    }

    /** removeItem удаляет ручную корзину, если позиций не осталось. */
    public function test_remove_item_returns_null_when_cart_becomes_empty(): void
    {
        [$customer, $manager] = $this->createCustomerAndManager();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);
        $service = app(ManualOrderCartService::class);
        $customerIdentity = $this->customerIdentity($customer);
        $managerIdentity = $this->managerIdentity($manager);

        $cart = $service->addItem($customerIdentity, $managerIdentity, $fixture['dish']->id, 1);
        $cartItemId = $cart->items[0]->id;

        $result = $service->removeItem($customerIdentity, $managerIdentity, $cartItemId);

        $this->assertNull($result);
        $this->assertNull($service->getDraftCart($customerIdentity, $managerIdentity));
        $this->assertDatabaseMissing('max_carts', [
            'max_user_id' => $customer->max_user_id,
            'created_by_max_user_id' => $manager->max_user_id,
        ]);
    }

    /** clear удаляет ручной черновик корзины. */
    public function test_clear_removes_manual_draft_cart(): void
    {
        [$customer, $manager] = $this->createCustomerAndManager();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 100);
        $service = app(ManualOrderCartService::class);
        $customerIdentity = $this->customerIdentity($customer);
        $managerIdentity = $this->managerIdentity($manager);

        $service->addItem($customerIdentity, $managerIdentity, $fixture['dish']->id, 1);
        $service->clear($customerIdentity, $managerIdentity);

        $this->assertNull($service->getDraftCart($customerIdentity, $managerIdentity));
        $this->assertSame(0, Cart::query()->count());
    }

    /**
     * @return array{0: MaxUser, 1: MaxUser}
     */
    private function createCustomerAndManager(): array
    {
        $customer = MaxUser::query()->create([
            'max_user_id' => 12_001,
            'first_name' => 'Client',
        ]);
        $manager = MaxUser::query()->create([
            'max_user_id' => 12_002,
            'first_name' => 'Manager',
        ]);

        return [$customer, $manager];
    }

    private function customerIdentity(MaxUser $customer): MaxUserIdentity
    {
        return new MaxUserIdentity(
            maxUserId: (int) $customer->max_user_id,
            adminRoles: [],
        );
    }

    private function managerIdentity(MaxUser $manager): MaxUserIdentity
    {
        return new MaxUserIdentity(
            maxUserId: (int) $manager->max_user_id,
            adminRoles: [FoodOrderAdminRole::MaxManager],
        );
    }
}
