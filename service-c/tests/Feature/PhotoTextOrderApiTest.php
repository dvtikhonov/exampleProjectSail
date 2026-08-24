<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Food\Review\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\Review\FoodOrderMaxNotifierInterface;
use App\Enums\Food\Order\OrderStatus;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Enums\Food\Review\OrderReviewStatus;
use App\Jobs\Food\NotifyFoodOrderAfterSubmitJob;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class PhotoTextOrderApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string AGENT_TOKEN = 'phototext-test-token';

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
        $this->mock(FoodOrderMaxNotifierInterface::class)->shouldIgnoreMissing();
        $this->mock(FoodOrderCustomerNotifierInterface::class)->shouldIgnoreMissing();
    }

    /** POST /orders создаёт запись в max_food_orders со статусом draft_after_scanning. */
    public function test_place_creates_manual_order_with_draft_after_scanning_status(): void
    {
        Bus::fake();

        $manager = $this->phototextManager();
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Обедов Тест',
            'Салат Оливье',
            80,
        );
        $customer = FoodTestDataBuilder::createMaxUserWithCategory(
            $fixture['customer_category'],
            maxUserId: 55_201,
            firstName: 'Сибирь-Финанс-PhotoText',
        );
        $customer->update(['delivery_address' => 'ул. Сканирования, 1']);

        $this->configurePhotoTextAgent($manager['user']->max_user_id);

        $response = $this->postJson('/api/food/phototext/orders', [
            'customer_query' => 'Сибирь-Финанс-PhotoText',
            'order_date' => '2026-08-14',
            'restaurant_id' => $fixture['restaurant']->id,
            'items' => [
                ['name' => 'Салат Оливье', 'quantity' => 2],
            ],
        ], $this->photoTextHeaders());

        $response
            ->assertCreated()
            ->assertJsonPath('matched_count', 1);

        $orderId = (int) $response->json('order_id');
        $this->assertGreaterThan(0, $orderId);

        $this->assertDatabaseHas('max_food_orders', [
            'id' => $orderId,
            'max_user_id' => $customer->max_user_id,
            'is_manual' => 1,
            'created_by_max_user_id' => $manager['user']->max_user_id,
            'restaurant_id' => $fixture['restaurant']->id,
            'status' => OrderStatus::DraftAfterScanning->value,
            'address_review_status' => OrderReviewStatus::Pending->value,
            'composition_review_status' => OrderReviewStatus::Pending->value,
            'payment_review_status' => OrderReviewStatus::Pending->value,
            'delivery_date' => '2026-08-14',
        ]);

        Bus::assertNotDispatched(NotifyFoodOrderAfterSubmitJob::class);
    }

    /** Черновик после сканирования создаётся даже без адреса доставки. */
    public function test_place_allows_empty_delivery_address_for_draft_after_scanning(): void
    {
        $manager = $this->phototextManager();
        $fixture = FoodTestDataBuilder::createRestaurantWithDishAndDelivery(
            'Обедов Без Адреса',
            'Бигус с курицей',
            170,
        );
        FoodTestDataBuilder::createMaxUserWithCategory(
            $fixture['customer_category'],
            maxUserId: 55_202,
            firstName: 'КлиентБезАдресаPhotoText',
        );

        $this->configurePhotoTextAgent($manager['user']->max_user_id);

        $response = $this->postJson('/api/food/phototext/orders', [
            'customer_query' => 'КлиентБезАдресаPhotoText',
            'order_date' => '2026-08-14',
            'restaurant_id' => $fixture['restaurant']->id,
            'items' => [
                ['name' => 'Бигус с курицей', 'quantity' => 1],
            ],
        ], $this->photoTextHeaders());

        $response->assertCreated();

        $this->assertDatabaseHas('max_food_orders', [
            'id' => $response->json('order_id'),
            'status' => OrderStatus::DraftAfterScanning->value,
            'delivery_address' => '',
        ]);
    }

    /**
     * @return array{user: MaxUser, headers: array<string, string>}
     */
    private function phototextManager(): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_016,
                'first_name' => 'PhotoTextManager',
            ])),
            FoodOrderAdminRole::MaxManager,
        );
    }

    private function configurePhotoTextAgent(int $managerMaxUserId): void
    {
        config([
            'phototext.agent_token' => self::AGENT_TOKEN,
            'phototext.manager_max_user_id' => $managerMaxUserId,
        ]);

        MaxUser::query()
            ->where('max_user_id', $managerMaxUserId)
            ->update([
                'ai_access_until' => Carbon::now()->addMinutes(30),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private function photoTextHeaders(): array
    {
        return [
            'X-PhotoText-Token' => self::AGENT_TOKEN,
        ];
    }
}
