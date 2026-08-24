<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Review\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\Review\FoodOrderMaxNotifierInterface;
use App\Contracts\Food\PhotoText\PhotoTextManualOrderPlacementServiceInterface;
use App\DTO\Food\PhotoText\PhotoTextAgentItemDto;
use App\Enums\Food\PhotoText\PhotoTextMatchIssueCode;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Food\CartItem;
use App\Models\Food\Dish;
use App\Models\Food\FoodOrder;
use App\Models\Food\MenuCategory;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class PhotoTextManualOrderPlacementServiceTest extends TestCase
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
        $this->mock(FoodOrderCustomerNotifierInterface::class)->shouldIgnoreMissing();
    }

    /** Блюдо другого ресторана даёт issue и не попадает в matched. */
    public function test_match_marks_dish_from_other_restaurant_as_issue(): void
    {
        $target = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Целевой',
            'Салат "Фасолька"',
            200,
        );
        FoodTestDataBuilder::createRestaurantWithDish('Чужой', 'Салат "Колизей"', 180);
        $this->createCustomer('Сибирь-Финанс', $target['customer_category']->id);

        $result = $this->placementService()->match(
            'Сибирь-Финанс',
            (int) $target['restaurant']->id,
            [
                new PhotoTextAgentItemDto(name: 'Салат "Фасолька"', quantity: 2),
                new PhotoTextAgentItemDto(name: 'Салат "Колизей"', quantity: 1),
            ],
        );

        $this->assertSame(1, $result->matchedCount);
        $this->assertSame('Салат "Фасолька"', $result->matched[0]->dishName);
        $this->assertCount(1, $result->issues);
        $this->assertSame(PhotoTextMatchIssueCode::DishNotFound, $result->issues[0]->code);
        $this->assertStringContainsString('Колизей', $result->issues[0]->message);
    }

    /** Строка со слэшем без combo_ref ищется как одно имя. */
    public function test_slash_name_without_combo_ref_is_single_not_found(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Целевой',
            'Филе минтая с овощами',
            300,
        );
        $this->createCustomer('Сибирь-Финанс', $fixture['customer_category']->id);

        $result = $this->placementService()->match(
            'Сибирь-Финанс',
            (int) $fixture['restaurant']->id,
            [
                new PhotoTextAgentItemDto(name: 'Филе минтая с овощами / Гречка', quantity: 1),
            ],
        );

        $this->assertSame(0, $result->matchedCount);
        $this->assertSame(PhotoTextMatchIssueCode::DishNotFound, $result->issues[0]->code);
    }

    /** Неполная пара combo_ref даёт combo_unresolved на обе строки. */
    public function test_incomplete_combo_ref_group_is_unresolved(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Целевой',
            'Гречка',
            80,
        );
        $this->createCustomer('Сибирь-Финанс', $fixture['customer_category']->id);

        $result = $this->placementService()->match(
            'Сибирь-Финанс',
            (int) $fixture['restaurant']->id,
            [
                new PhotoTextAgentItemDto(
                    name: 'Гречка',
                    quantity: 1,
                    comboRef: '11111111-1111-1111-1111-111111111111',
                ),
            ],
        );

        $this->assertSame(0, $result->matchedCount);
        $this->assertCount(1, $result->issues);
        $this->assertSame(PhotoTextMatchIssueCode::ComboUnresolved, $result->issues[0]->code);
    }

    /** Place пишет delivery_date из промпта и две позиции корзины для combo_ref. */
    public function test_place_writes_prompt_delivery_date_and_combo_cart_items(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Целевой',
            'Салат "Фасолька"',
            200,
        );
        $hotCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Горячее',
            'sort_order' => 2,
            'is_combo_available' => true,
        ]);
        $sideCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Гарнир',
            'sort_order' => 3,
            'is_combo_available' => true,
        ]);
        $hot = Dish::factory()->create([
            'menu_category_id' => $hotCategory->id,
            'name' => 'Филе минтая с овощами',
            'price' => 350,
        ]);
        $side = Dish::factory()->create([
            'menu_category_id' => $sideCategory->id,
            'name' => 'Гречка',
            'price' => 80,
        ]);
        $this->createCustomer('Сибирь-Финанс', $fixture['customer_category']->id, 'ул. Клиента, 1');
        $manager = $this->seedManager(77_001);
        $comboRef = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

        $result = $this->placementService()->place(
            'Сибирь-Финанс',
            '2026-08-17',
            (int) $fixture['restaurant']->id,
            [
                new PhotoTextAgentItemDto(name: 'Салат "Фасолька"', quantity: 2),
                new PhotoTextAgentItemDto(name: 'Филе минтая с овощами', quantity: 1, comboRef: $comboRef),
                new PhotoTextAgentItemDto(name: 'Гречка', quantity: 1, comboRef: $comboRef),
            ],
        );

        $this->assertNotNull($result->orderId);
        $this->assertSame(3, $result->matchedCount);
        $this->assertSame([], $result->issues);

        $order = FoodOrder::query()->findOrFail($result->orderId);
        $this->assertSame('2026-08-17', $order->delivery_date?->format('Y-m-d'));
        $this->assertTrue($order->is_manual);
        $this->assertSame($manager->max_user_id, $order->created_by_max_user_id);

        $comboItems = CartItem::query()
            ->where('cart_id', $order->cart_id)
            ->where('combo_ref', $comboRef)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $comboItems);
        $this->assertSame($hot->id, $comboItems[0]->dish_id);
        $this->assertSame($side->id, $comboItems[0]->combo_partner_dish_id);
        $this->assertSame($side->id, $comboItems[1]->dish_id);
        $this->assertSame($hot->id, $comboItems[1]->combo_partner_dish_id);
    }

    /** Пустой matched при place не создаёт заказ. */
    public function test_place_returns_report_without_order_when_matched_empty(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Целевой',
            'Салат "Фасолька"',
            200,
        );
        $this->createCustomer('Сибирь-Финанс', $fixture['customer_category']->id, 'ул. Клиента, 1');

        $result = $this->placementService()->place(
            'Сибирь-Финанс',
            '2026-08-17',
            (int) $fixture['restaurant']->id,
            [
                new PhotoTextAgentItemDto(name: 'Несуществующее блюдо', quantity: 1),
            ],
        );

        $this->assertNull($result->orderId);
        $this->assertSame(0, $result->matchedCount);
        $this->assertSame(PhotoTextMatchIssueCode::DishNotFound, $result->issues[0]->code);
        $this->assertSame(0, FoodOrder::query()->count());
    }

    /** Неоднозначный клиент — STOP. */
    public function test_match_throws_when_several_customers_found(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Целевой',
            'Салат "Фасолька"',
            200,
        );
        MaxUser::query()->create([
            'max_user_id' => 66_010,
            'first_name' => 'Сибирь-Финанс',
        ]);
        MaxUser::query()->create([
            'max_user_id' => 66_011,
            'first_name' => 'Сибирь-Финанс филиал',
        ]);

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('Найдено несколько клиентов');

        $this->placementService()->match(
            'Сибирь-Финанс',
            (int) $fixture['restaurant']->id,
            [
                new PhotoTextAgentItemDto(name: 'Салат "Фасолька"', quantity: 1),
            ],
        );
    }

    private function placementService(): PhotoTextManualOrderPlacementServiceInterface
    {
        return $this->app->make(PhotoTextManualOrderPlacementServiceInterface::class);
    }

    /** Менеджер PhotoText с ролью max_manager. */
    private function seedManager(int $maxUserId): MaxUser
    {
        $manager = MaxUser::query()->create([
            'max_user_id' => $maxUserId,
            'first_name' => 'PhotoTextManager',
        ]);
        $this->asFoodOrderAdmin(
            ['user' => $manager, 'headers' => []],
            FoodOrderAdminRole::MaxManager,
        );
        config(['phototext.manager_max_user_id' => $manager->max_user_id]);

        return $manager;
    }

    private function createCustomer(
        string $firstName,
        int $customerCategoryId,
        string $deliveryAddress = 'ул. Тестовая, 1',
    ): MaxUser {
        return MaxUser::query()->create([
            'max_user_id' => 66_001,
            'first_name' => $firstName,
            'customer_category_id' => $customerCategoryId,
            'delivery_address' => $deliveryAddress,
        ]);
    }
}
