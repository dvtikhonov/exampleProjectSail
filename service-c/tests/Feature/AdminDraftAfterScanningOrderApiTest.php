<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Food\Review\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\Review\FoodOrderMaxNotifierInterface;
use App\Enums\Food\Cart\CartStatus;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Enums\Food\Review\OrderReviewStatus;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Models\Food\Cart;
use App\Models\Food\Dish;
use App\Models\Food\FoodOrder;
use App\Models\Food\FoodOrderMessage;
use App\Models\Food\MenuCategory;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class AdminDraftAfterScanningOrderApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
        $this->mock(FoodOrderMaxNotifierInterface::class)->shouldIgnoreMissing();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function actionMethods(): array
    {
        return [
            'complete' => ['POST', 'complete'],
            'move-to-cart' => ['POST', 'move-to-cart'],
            'delete' => ['DELETE', ''],
        ];
    }

    #[DataProvider('actionMethods')]
    /** Действия с черновиком после сканирования требуют аутентификацию. */
    public function test_draft_after_scanning_actions_require_authentication(
        string $method,
        string $suffix,
    ): void {
        $this->requestAction($method, $suffix, 1)->assertUnauthorized();
    }

    #[DataProvider('actionMethods')]
    /** Без роли max_manager действия недоступны. */
    public function test_draft_after_scanning_actions_forbidden_without_max_manager_role(
        string $method,
        string $suffix,
    ): void {
        $auth = $this->authenticateMaxUser();

        $this->requestAction($method, $suffix, 1, $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Доступ запрещён.');
    }

    #[DataProvider('actionMethods')]
    /** Роль menu_manager не открывает действия черновика после сканирования. */
    public function test_draft_after_scanning_actions_forbidden_with_menu_manager_role(
        string $method,
        string $suffix,
    ): void {
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 56_005,
                'first_name' => 'MenuManager',
            ])),
            FoodOrderAdminRole::MenuManager,
        );

        $this->requestAction($method, $suffix, 1, $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Доступ запрещён.');
    }

    /** complete переводит заказ в «Выполнен», approves проверки и уведомляет оформившего. */
    public function test_complete_confirms_order_approves_reviews_and_notifies_creator(): void
    {
        $manager = $this->maxManagerAuth();
        $creator = MaxUser::query()->create([
            'max_user_id' => 56_201,
            'first_name' => 'Creator',
            'last_name' => 'Manager',
        ]);
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Scan Place', 'Soup', 200);
        $customer = MaxUser::query()->create([
            'max_user_id' => 56_101,
            'first_name' => 'Иван',
            'last_name' => 'Петров',
        ]);
        $order = $this->createManualOrder(
            restaurantId: $fixture['restaurant']->id,
            customerMaxUserId: $customer->max_user_id,
            managerMaxUserId: $creator->max_user_id,
            dish: $fixture['dish'],
        );

        $capturedOrder = null;
        $customerNotifier = $this->createMock(FoodOrderCustomerNotifierInterface::class);
        $customerNotifier
            ->expects($this->once())
            ->method('notifyManualOrderCreatorConfirmed')
            ->willReturnCallback(function (FoodOrderRecord $notifiedOrder) use (&$capturedOrder): void {
                $capturedOrder = $notifiedOrder;
            });
        $customerNotifier->expects($this->never())->method('notifyConfirmed');
        $customerNotifier->expects($this->never())->method('notifySubmitted');
        $this->app->instance(FoodOrderCustomerNotifierInterface::class, $customerNotifier);

        $this->postJson(
            '/api/food/admin/manual-orders/'.$order->id.'/complete',
            [],
            $manager['headers'],
        )
            ->assertOk()
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonPath('order.status', OrderStatus::Confirmed->value);

        $this->assertDatabaseHas('max_food_orders', [
            'id' => $order->id,
            'status' => OrderStatus::Confirmed->value,
            'address_review_status' => OrderReviewStatus::Approved->value,
            'composition_review_status' => OrderReviewStatus::Approved->value,
            'payment_review_status' => OrderReviewStatus::Approved->value,
            'address_reviewed_by' => $manager['user']->max_user_id,
            'composition_reviewed_by' => $manager['user']->max_user_id,
            'payment_reviewed_by' => $manager['user']->max_user_id,
        ]);

        $freshOrder = $order->fresh();
        $this->assertNotNull($freshOrder?->address_reviewed_at);
        $this->assertNotNull($freshOrder?->composition_reviewed_at);
        $this->assertNotNull($freshOrder?->payment_reviewed_at);

        $this->assertNotNull($capturedOrder);
        $this->assertSame($order->id, $capturedOrder->id);
        $this->assertSame($creator->max_user_id, $capturedOrder->createdByMaxUserId);
        $this->assertSame(OrderStatus::Confirmed, $capturedOrder->status);
    }

    /** move-to-cart заполняет ручную корзину, очищает старый draft и удаляет заказ. */
    public function test_move_to_cart_fills_cart_clears_old_draft_and_deletes_order(): void
    {
        $manager = $this->maxManagerAuth();
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Scan Cart Place',
            'Soup',
            200,
        );
        $burger = Dish::factory()->create([
            'menu_category_id' => $fixture['category']->id,
            'name' => 'Burger',
            'price' => 320,
        ]);
        $sideCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Sides',
            'sort_order' => 2,
        ]);
        $sideDish = Dish::factory()->create([
            'menu_category_id' => $sideCategory->id,
            'name' => 'Fries',
            'price' => 150,
        ]);
        $customer = FoodTestDataBuilder::createMaxUserWithCategory(
            $fixture['customer_category'],
            maxUserId: 56_102,
            firstName: 'CartCustomer',
        );
        $comboRef = '550e8400-e29b-41d4-a716-446655440000';
        $deliveryAddress = 'ул. Скана, 15';

        $order = $this->createManualOrder(
            restaurantId: $fixture['restaurant']->id,
            customerMaxUserId: $customer->max_user_id,
            managerMaxUserId: $manager['user']->max_user_id,
            dish: $fixture['dish'],
            quantity: 2,
            deliveryAddress: $deliveryAddress,
            deliveryDate: '2026-08-14',
            extraSnapshotItems: [
                [
                    'dish_id' => $burger->id,
                    'dish_name' => $burger->name,
                    'quantity' => 1,
                    'unit_price' => '320.00',
                    'line_total' => '320.00',
                    'combo_ref' => $comboRef,
                    'combo_partner_dish_ids' => [$sideDish->id],
                ],
                [
                    'dish_id' => $sideDish->id,
                    'dish_name' => $sideDish->name,
                    'quantity' => 1,
                    'unit_price' => '150.00',
                    'line_total' => '150.00',
                    'combo_ref' => $comboRef,
                    'combo_partner_dish_ids' => [$burger->id],
                ],
            ],
        );

        $oldDraftResponse = $this->addManualCartItem(
            $manager,
            $customer->max_user_id,
            $fixture['dish']->id,
            9,
        )->assertOk();
        $oldDraftCartId = (int) $oldDraftResponse->json('cart.id');

        $response = $this->postJson(
            '/api/food/admin/manual-orders/'.$order->id.'/move-to-cart',
            [],
            $manager['headers'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('customer.max_user_id', $customer->max_user_id)
            ->assertJsonPath('delivery_address', $deliveryAddress)
            ->assertJsonPath('delivery_date', '2026-08-14')
            ->assertJsonPath('cart.delivery_date', '2026-08-14')
            ->assertJsonPath('cart.status', CartStatus::Draft->value)
            ->assertJsonPath('cart.restaurant_id', $fixture['restaurant']->id);

        $items = collect($response->json('cart.items'))->keyBy('dish_id');
        $this->assertCount(3, $items);
        $this->assertSame(2, $items[$fixture['dish']->id]['quantity']);
        $this->assertArrayNotHasKey('combo_ref', $items[$fixture['dish']->id]);
        $this->assertSame(1, $items[$burger->id]['quantity']);
        $this->assertSame($comboRef, $items[$burger->id]['combo_ref']);
        $this->assertSame($sideDish->id, $items[$burger->id]['combo_partner_dish_id']);
        $this->assertSame(1, $items[$sideDish->id]['quantity']);
        $this->assertSame($comboRef, $items[$sideDish->id]['combo_ref']);
        $this->assertSame($burger->id, $items[$sideDish->id]['combo_partner_dish_id']);

        $this->assertDatabaseMissing('max_food_orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('max_carts', ['id' => $oldDraftCartId]);
        $this->assertDatabaseHas('max_carts', [
            'id' => (int) $response->json('cart.id'),
            'max_user_id' => $customer->max_user_id,
            'created_by_max_user_id' => $manager['user']->max_user_id,
            'status' => CartStatus::Draft->value,
            'delivery_address' => $deliveryAddress,
        ]);
        $this->assertDatabaseHas('max_users', [
            'max_user_id' => $customer->max_user_id,
            'delivery_address' => $deliveryAddress,
        ]);
        $this->assertDatabaseHas('max_cart_items', [
            'cart_id' => (int) $response->json('cart.id'),
            'dish_id' => $fixture['dish']->id,
            'quantity' => 2,
            'combo_ref' => null,
            'combo_partner_dish_id' => null,
        ]);
        $this->assertDatabaseHas('max_cart_items', [
            'cart_id' => (int) $response->json('cart.id'),
            'dish_id' => $burger->id,
            'quantity' => 1,
            'combo_ref' => $comboRef,
            'combo_partner_dish_id' => $sideDish->id,
        ]);
        $this->assertDatabaseHas('max_cart_items', [
            'cart_id' => (int) $response->json('cart.id'),
            'dish_id' => $sideDish->id,
            'quantity' => 1,
            'combo_ref' => $comboRef,
            'combo_partner_dish_id' => $burger->id,
        ]);
    }

    /** delete удаляет заказ без восстановления, чат уходит каскадом. */
    public function test_delete_removes_draft_after_scanning_order(): void
    {
        $manager = $this->maxManagerAuth();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Delete Place', 'Tea', 100);
        $customer = MaxUser::query()->create([
            'max_user_id' => 56_103,
            'first_name' => 'DeleteCustomer',
        ]);
        $order = $this->createManualOrder(
            restaurantId: $fixture['restaurant']->id,
            customerMaxUserId: $customer->max_user_id,
            managerMaxUserId: $manager['user']->max_user_id,
            dish: $fixture['dish'],
        );
        FoodOrderMessage::query()->create([
            'food_order_id' => $order->id,
            'sender_max_user_id' => $customer->max_user_id,
            'body' => 'Черновик',
        ]);

        $this->deleteJson(
            '/api/food/admin/manual-orders/'.$order->id,
            [],
            $manager['headers'],
        )->assertNoContent();

        $this->assertDatabaseMissing('max_food_orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('max_food_order_messages', [
            'food_order_id' => $order->id,
        ]);
    }

    #[DataProvider('actionMethods')]
    /** Действия отклоняются, если статус не «Черновик после сканирования». */
    public function test_actions_reject_order_not_in_draft_after_scanning(
        string $method,
        string $suffix,
    ): void {
        $manager = $this->maxManagerAuth();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Wrong Status Place', 'Soup', 180);
        $customer = MaxUser::query()->create([
            'max_user_id' => 56_104,
            'first_name' => 'WrongStatus',
        ]);
        $order = $this->createManualOrder(
            restaurantId: $fixture['restaurant']->id,
            customerMaxUserId: $customer->max_user_id,
            managerMaxUserId: $manager['user']->max_user_id,
            dish: $fixture['dish'],
            status: OrderStatus::Confirmed,
            reviewStatus: OrderReviewStatus::Approved,
        );

        $customerNotifier = $this->createMock(FoodOrderCustomerNotifierInterface::class);
        $customerNotifier->expects($this->never())->method('notifyManualOrderCreatorConfirmed');
        $this->app->instance(FoodOrderCustomerNotifierInterface::class, $customerNotifier);

        $this->requestAction($method, $suffix, $order->id, $manager['headers'])
            ->assertUnprocessable()
            ->assertJsonPath(
                'message',
                'Действие доступно только для заказа в статусе «Черновик после сканирования».',
            );

        $this->assertDatabaseHas('max_food_orders', [
            'id' => $order->id,
            'status' => OrderStatus::Confirmed->value,
        ]);
    }

    #[DataProvider('actionMethods')]
    /** Не-ручной заказ недоступен для действий черновика после сканирования. */
    public function test_actions_reject_non_manual_order(string $method, string $suffix): void
    {
        $manager = $this->maxManagerAuth();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Regular Place', 'Soup', 180);
        $customer = MaxUser::query()->create([
            'max_user_id' => 56_105,
            'first_name' => 'Regular',
        ]);
        $order = $this->createManualOrder(
            restaurantId: $fixture['restaurant']->id,
            customerMaxUserId: $customer->max_user_id,
            managerMaxUserId: $manager['user']->max_user_id,
            dish: $fixture['dish'],
            isManual: false,
        );

        $this->requestAction($method, $suffix, $order->id, $manager['headers'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Заказ не найден.');
    }

    /**
     * @return array{user: MaxUser, headers: array<string, string>}
     */
    private function maxManagerAuth(int $maxUserId = 10_016): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => $maxUserId,
                'first_name' => 'MaxManager',
            ])),
            FoodOrderAdminRole::MaxManager,
        );
    }

    /**
     * @param  array{headers: array<string, string>}  $manager
     */
    private function addManualCartItem(
        array $manager,
        int $customerMaxUserId,
        int $dishId,
        int $quantity,
    ): TestResponse {
        return $this->postJson('/api/food/admin/manual-orders/cart/items', [
            'max_user_id' => $customerMaxUserId,
            'dish_id' => $dishId,
            'quantity' => $quantity,
        ], $manager['headers']);
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private function requestAction(
        string $method,
        string $suffix,
        int $orderId,
        array $headers = [],
    ): TestResponse {
        $url = '/api/food/admin/manual-orders/'.$orderId;

        if ($suffix !== '') {
            $url .= '/'.$suffix;
        }

        return $this->json($method, $url, [], $headers);
    }

    /**
     * Создаёт ручной заказ для тестов действий «Черновик после сканирования».
     *
     * @param  list<array<string, mixed>>  $extraSnapshotItems
     */
    private function createManualOrder(
        int $restaurantId,
        int $customerMaxUserId,
        int $managerMaxUserId,
        Dish $dish,
        int $quantity = 1,
        OrderStatus $status = OrderStatus::DraftAfterScanning,
        OrderReviewStatus $reviewStatus = OrderReviewStatus::Pending,
        string $deliveryAddress = 'ул. Скана, 15',
        ?string $deliveryDate = null,
        bool $isManual = true,
        array $extraSnapshotItems = [],
    ): FoodOrder {
        $unitPrice = number_format((float) $dish->price, 2, '.', '');
        $lineTotal = number_format((float) $dish->price * $quantity, 2, '.', '');

        $firstItem = [
            'dish_id' => $dish->id,
            'dish_name' => $dish->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ];

        $cart = Cart::query()->create([
            'max_user_id' => $customerMaxUserId,
            'created_by_max_user_id' => $isManual ? $managerMaxUserId : null,
            'restaurant_id' => $restaurantId,
            'status' => CartStatus::Submitted,
            'delivery_address' => $deliveryAddress,
        ]);

        return FoodOrder::query()->create([
            'cart_id' => $cart->id,
            'max_user_id' => $customerMaxUserId,
            'is_manual' => $isManual,
            'created_by_max_user_id' => $isManual ? $managerMaxUserId : null,
            'restaurant_id' => $restaurantId,
            'status' => $status,
            'address_review_status' => $reviewStatus,
            'composition_review_status' => $reviewStatus,
            'payment_review_status' => $reviewStatus,
            'total' => $lineTotal,
            'items_total' => $lineTotal,
            'delivery_cost' => 0,
            'delivery_address' => $deliveryAddress,
            'delivery_date' => $deliveryDate,
            'items_snapshot' => [$firstItem, ...$extraSnapshotItems],
        ]);
    }
}
