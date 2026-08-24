<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\PhotoText\PhotoTextMatchIssueCode;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Food\Dish;
use App\Models\Food\DishAvailabilityDate;
use App\Models\Food\MenuCategory;
use App\Models\Max\MaxUser;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class PhotoTextScheduleApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string AGENT_TOKEN = 'phototext-schedule-test-token';

    private const string TIMEZONE = 'Europe/Moscow';

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** Без X-PhotoText-Token schedule endpoints возвращают 401. */
    public function test_schedule_endpoints_return_unauthorized_without_token(): void
    {
        $payload = $this->validPayload(restaurantId: 1);

        $this->postJson('/api/food/phototext/schedule/match', $payload)
            ->assertUnauthorized();

        $this->postJson('/api/food/phototext/schedule/apply', $payload)
            ->assertUnauthorized();
    }

    /** Без активного AI-доступа max_manager schedule endpoints возвращают 403. */
    public function test_schedule_endpoints_return_forbidden_without_ai_access(): void
    {
        config(['phototext.agent_token' => self::AGENT_TOKEN]);

        $payload = $this->validPayload(restaurantId: 1);

        $this->postJson('/api/food/phototext/schedule/match', $payload, $this->photoTextHeaders())
            ->assertForbidden()
            ->assertJsonPath('message', 'Доступ AI к базе не разрешён.');

        $this->postJson('/api/food/phototext/schedule/apply', $payload, $this->photoTextHeaders())
            ->assertForbidden()
            ->assertJsonPath('message', 'Доступ AI к базе не разрешён.');
    }

    /** Match без category_id сопоставляет блюда по всему каталогу ресторана. */
    public function test_match_without_category_id_resolves_dishes_in_restaurant(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-20 12:00:00', self::TIMEZONE));
        $manager = $this->phototextManager();
        $this->configurePhotoTextAgent($manager['user']->max_user_id);

        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Schedule Cafe', 'Борщ', 150);
        $sideCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Гарниры',
            'sort_order' => 2,
        ]);
        $sideDish = Dish::factory()->create([
            'menu_category_id' => $sideCategory->id,
            'name' => 'Гречка',
            'price' => 80,
        ]);

        $dates = $this->weekWindowFrom('2026-08-20');

        $response = $this->postJson('/api/food/phototext/schedule/match', [
            'restaurant_id' => $fixture['restaurant']->id,
            'category_id' => null,
            'date_from' => $dates['date_from'],
            'date_to' => $dates['date_to'],
            'entries' => [
                ['name' => 'Борщ', 'dates' => [$dates['date_from']]],
                ['name' => 'Гречка', 'dates' => [$dates['day_2']]],
                ['name' => 'Несуществующее', 'dates' => [$dates['date_from']]],
            ],
        ], $this->photoTextHeaders());

        $response
            ->assertOk()
            ->assertJsonPath('matched_count', 2)
            ->assertJsonPath('applied', false)
            ->assertJsonPath('categories_applied', [])
            ->assertJsonPath('date_from', $dates['date_from'])
            ->assertJsonPath('date_to', $dates['date_to'])
            ->assertJsonPath('matched.0.dish_id', $fixture['dish']->id)
            ->assertJsonPath('matched.0.category_id', $fixture['category']->id)
            ->assertJsonPath('matched.1.dish_id', $sideDish->id)
            ->assertJsonPath('matched.1.category_id', $sideCategory->id)
            ->assertJsonPath('issues.0.code', PhotoTextMatchIssueCode::DishNotFound->value)
            ->assertJsonPath('issues.0.raw_title', 'Несуществующее');

        $this->assertDatabaseCount('max_dish_availability_dates', 0);
    }

    /** Match с category_id ограничивает поиск блюдами этой категории. */
    public function test_match_with_category_filter_excludes_other_categories(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-20 12:00:00', self::TIMEZONE));
        $manager = $this->phototextManager();
        $this->configurePhotoTextAgent($manager['user']->max_user_id);

        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Filter Cafe', 'Салат Цезарь', 200);
        $sideCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Гарниры',
            'sort_order' => 2,
        ]);
        Dish::factory()->create([
            'menu_category_id' => $sideCategory->id,
            'name' => 'Гречка',
            'price' => 80,
        ]);

        $dates = $this->weekWindowFrom('2026-08-20');

        $response = $this->postJson('/api/food/phototext/schedule/match', [
            'restaurant_id' => $fixture['restaurant']->id,
            'category_id' => $fixture['category']->id,
            'date_from' => $dates['date_from'],
            'date_to' => $dates['date_to'],
            'entries' => [
                ['name' => 'Салат Цезарь', 'dates' => [$dates['date_from']]],
                ['name' => 'Гречка', 'dates' => [$dates['day_2']]],
            ],
        ], $this->photoTextHeaders());

        $response
            ->assertOk()
            ->assertJsonPath('matched_count', 1)
            ->assertJsonPath('matched.0.dish_name', 'Салат Цезарь')
            ->assertJsonPath('matched.0.category_id', $fixture['category']->id)
            ->assertJsonPath('issues.0.code', PhotoTextMatchIssueCode::DishNotFound->value)
            ->assertJsonPath('issues.0.raw_title', 'Гречка')
            ->assertJsonPath('issues.0.message', 'Блюдо не относится к указанной категории: Гречка');
    }

    /** Одно имя в двух категориях ресторана даёт dish_ambiguous. */
    public function test_match_marks_ambiguous_dish_across_categories(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-20 12:00:00', self::TIMEZONE));
        $manager = $this->phototextManager();
        $this->configurePhotoTextAgent($manager['user']->max_user_id);

        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Ambiguous Cafe', 'Оливье', 180);
        $otherCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Холодные',
            'sort_order' => 2,
        ]);
        Dish::factory()->create([
            'menu_category_id' => $otherCategory->id,
            'name' => 'Оливье',
            'price' => 190,
        ]);

        $dates = $this->weekWindowFrom('2026-08-20');

        $this->postJson('/api/food/phototext/schedule/match', [
            'restaurant_id' => $fixture['restaurant']->id,
            'date_from' => $dates['date_from'],
            'date_to' => $dates['date_to'],
            'entries' => [
                ['name' => 'Оливье', 'dates' => [$dates['date_from']]],
            ],
        ], $this->photoTextHeaders())
            ->assertOk()
            ->assertJsonPath('matched_count', 0)
            ->assertJsonPath('issues.0.code', PhotoTextMatchIssueCode::DishAmbiguous->value)
            ->assertJsonPath('issues.0.raw_title', 'Оливье');
    }

    /** Apply пишет строки в max_dish_availability_dates и заменяет все категории ресторана. */
    public function test_apply_writes_availability_dates_and_groups_by_category(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-20 12:00:00', self::TIMEZONE));
        $manager = $this->phototextManager();
        $this->configurePhotoTextAgent($manager['user']->max_user_id);

        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Apply Cafe', 'Борщ', 150);
        $sideCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Гарниры',
            'sort_order' => 2,
        ]);
        $sideDish = Dish::factory()->create([
            'menu_category_id' => $sideCategory->id,
            'name' => 'Гречка',
            'price' => 80,
        ]);

        $dates = $this->weekWindowFrom('2026-08-20');

        $response = $this->postJson('/api/food/phototext/schedule/apply', [
            'restaurant_id' => $fixture['restaurant']->id,
            'date_from' => $dates['date_from'],
            'date_to' => $dates['date_to'],
            'entries' => [
                ['name' => 'Борщ', 'dates' => [$dates['date_from'], $dates['day_2']]],
                ['name' => 'Гречка', 'dates' => [$dates['day_3']]],
                ['name' => 'Нет в меню', 'dates' => [$dates['date_from']]],
            ],
        ], $this->photoTextHeaders());

        $categoryIds = [$fixture['category']->id, $sideCategory->id];
        sort($categoryIds);

        $response
            ->assertOk()
            ->assertJsonPath('matched_count', 2)
            ->assertJsonPath('applied', true)
            ->assertJsonPath('categories_applied', $categoryIds)
            ->assertJsonPath('issues.0.code', PhotoTextMatchIssueCode::DishNotFound->value);

        $this->assertDatabaseHas('max_dish_availability_dates', [
            'dish_id' => $fixture['dish']->id,
            'available_date' => $dates['date_from'],
        ]);
        $this->assertDatabaseHas('max_dish_availability_dates', [
            'dish_id' => $fixture['dish']->id,
            'available_date' => $dates['day_2'],
        ]);
        $this->assertDatabaseHas('max_dish_availability_dates', [
            'dish_id' => $sideDish->id,
            'available_date' => $dates['day_3'],
        ]);
        $this->assertSame(3, DishAvailabilityDate::query()->count());
    }

    /** Apply без category_id удаляет ранее записанные даты в окне у блюд вне entries. */
    public function test_apply_clears_previous_schedule_for_dishes_not_in_entries(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-20 12:00:00', self::TIMEZONE));
        $manager = $this->phototextManager();
        $this->configurePhotoTextAgent($manager['user']->max_user_id);

        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Replace Cafe', 'Борщ', 150);
        $oldDish = Dish::factory()->create([
            'menu_category_id' => $fixture['category']->id,
            'name' => 'Щи',
            'price' => 140,
        ]);
        $sideCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Гарниры',
            'sort_order' => 2,
        ]);
        $sideDish = Dish::factory()->create([
            'menu_category_id' => $sideCategory->id,
            'name' => 'Гречка',
            'price' => 80,
        ]);

        $dates = $this->weekWindowFrom('2026-08-20');

        DishAvailabilityDate::query()->create([
            'dish_id' => $oldDish->id,
            'available_date' => $dates['date_from'],
        ]);
        DishAvailabilityDate::query()->create([
            'dish_id' => $sideDish->id,
            'available_date' => $dates['day_2'],
        ]);
        DishAvailabilityDate::query()->create([
            'dish_id' => $fixture['dish']->id,
            'available_date' => $dates['day_3'],
        ]);

        $this->postJson('/api/food/phototext/schedule/apply', [
            'restaurant_id' => $fixture['restaurant']->id,
            'date_from' => $dates['date_from'],
            'date_to' => $dates['date_to'],
            'entries' => [
                ['name' => 'Борщ', 'dates' => [$dates['date_from']]],
            ],
        ], $this->photoTextHeaders())
            ->assertOk()
            ->assertJsonPath('applied', true)
            ->assertJsonPath('matched_count', 1);

        $this->assertDatabaseHas('max_dish_availability_dates', [
            'dish_id' => $fixture['dish']->id,
            'available_date' => $dates['date_from'],
        ]);
        $this->assertDatabaseMissing('max_dish_availability_dates', [
            'dish_id' => $fixture['dish']->id,
            'available_date' => $dates['day_3'],
        ]);
        $this->assertDatabaseMissing('max_dish_availability_dates', [
            'dish_id' => $oldDish->id,
            'available_date' => $dates['date_from'],
        ]);
        $this->assertDatabaseMissing('max_dish_availability_dates', [
            'dish_id' => $sideDish->id,
            'available_date' => $dates['day_2'],
        ]);
        $this->assertSame(1, DishAvailabilityDate::query()->count());
    }

    /** Apply с category_ids очищает только указанные категории, остальные не трогает. */
    public function test_apply_with_category_ids_clears_only_those_categories(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-20 12:00:00', self::TIMEZONE));
        $manager = $this->phototextManager();
        $this->configurePhotoTextAgent($manager['user']->max_user_id);

        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Multi Scope Cafe', 'Борщ', 150);
        $oldDish = Dish::factory()->create([
            'menu_category_id' => $fixture['category']->id,
            'name' => 'Щи',
            'price' => 140,
        ]);
        $sideCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Гарниры',
            'sort_order' => 2,
        ]);
        $sideDish = Dish::factory()->create([
            'menu_category_id' => $sideCategory->id,
            'name' => 'Гречка',
            'price' => 80,
        ]);
        $dessertCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Десерты',
            'sort_order' => 3,
        ]);
        $dessertDish = Dish::factory()->create([
            'menu_category_id' => $dessertCategory->id,
            'name' => 'Торт',
            'price' => 220,
        ]);

        $dates = $this->weekWindowFrom('2026-08-20');

        DishAvailabilityDate::query()->create([
            'dish_id' => $oldDish->id,
            'available_date' => $dates['date_from'],
        ]);
        DishAvailabilityDate::query()->create([
            'dish_id' => $sideDish->id,
            'available_date' => $dates['day_2'],
        ]);
        DishAvailabilityDate::query()->create([
            'dish_id' => $dessertDish->id,
            'available_date' => $dates['day_3'],
        ]);

        $categoryIds = [$fixture['category']->id, $sideCategory->id];
        sort($categoryIds);

        $this->postJson('/api/food/phototext/schedule/apply', [
            'restaurant_id' => $fixture['restaurant']->id,
            'category_ids' => [$fixture['category']->id, $sideCategory->id],
            'date_from' => $dates['date_from'],
            'date_to' => $dates['date_to'],
            'entries' => [
                ['name' => 'Борщ', 'dates' => [$dates['date_from']]],
                ['name' => 'Гречка', 'dates' => [$dates['day_2']]],
            ],
        ], $this->photoTextHeaders())
            ->assertOk()
            ->assertJsonPath('applied', true)
            ->assertJsonPath('matched_count', 2)
            ->assertJsonPath('categories_applied', $categoryIds);

        $this->assertDatabaseHas('max_dish_availability_dates', [
            'dish_id' => $fixture['dish']->id,
            'available_date' => $dates['date_from'],
        ]);
        $this->assertDatabaseHas('max_dish_availability_dates', [
            'dish_id' => $sideDish->id,
            'available_date' => $dates['day_2'],
        ]);
        $this->assertDatabaseMissing('max_dish_availability_dates', [
            'dish_id' => $oldDish->id,
            'available_date' => $dates['date_from'],
        ]);
        $this->assertDatabaseHas('max_dish_availability_dates', [
            'dish_id' => $dessertDish->id,
            'available_date' => $dates['day_3'],
        ]);
        $this->assertSame(3, DishAvailabilityDate::query()->count());
    }

    /** Apply с category_id очищает только эту категорию, другие не трогает. */
    public function test_apply_with_category_id_clears_only_that_category(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-20 12:00:00', self::TIMEZONE));
        $manager = $this->phototextManager();
        $this->configurePhotoTextAgent($manager['user']->max_user_id);

        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Scoped Cafe', 'Борщ', 150);
        $oldDish = Dish::factory()->create([
            'menu_category_id' => $fixture['category']->id,
            'name' => 'Щи',
            'price' => 140,
        ]);
        $sideCategory = MenuCategory::factory()->create([
            'restaurant_id' => $fixture['restaurant']->id,
            'name' => 'Гарниры',
            'sort_order' => 2,
        ]);
        $sideDish = Dish::factory()->create([
            'menu_category_id' => $sideCategory->id,
            'name' => 'Гречка',
            'price' => 80,
        ]);

        $dates = $this->weekWindowFrom('2026-08-20');

        DishAvailabilityDate::query()->create([
            'dish_id' => $oldDish->id,
            'available_date' => $dates['date_from'],
        ]);
        DishAvailabilityDate::query()->create([
            'dish_id' => $sideDish->id,
            'available_date' => $dates['day_2'],
        ]);

        $this->postJson('/api/food/phototext/schedule/apply', [
            'restaurant_id' => $fixture['restaurant']->id,
            'category_id' => $fixture['category']->id,
            'date_from' => $dates['date_from'],
            'date_to' => $dates['date_to'],
            'entries' => [
                ['name' => 'Борщ', 'dates' => [$dates['date_from']]],
            ],
        ], $this->photoTextHeaders())
            ->assertOk()
            ->assertJsonPath('applied', true)
            ->assertJsonPath('categories_applied', [$fixture['category']->id]);

        $this->assertDatabaseHas('max_dish_availability_dates', [
            'dish_id' => $fixture['dish']->id,
            'available_date' => $dates['date_from'],
        ]);
        $this->assertDatabaseMissing('max_dish_availability_dates', [
            'dish_id' => $oldDish->id,
            'available_date' => $dates['date_from'],
        ]);
        $this->assertDatabaseHas('max_dish_availability_dates', [
            'dish_id' => $sideDish->id,
            'available_date' => $dates['day_2'],
        ]);
        $this->assertSame(2, DishAvailabilityDate::query()->count());
    }

    /** Apply с пустым matched возвращает 422 и не пишет график. */
    public function test_apply_returns_unprocessable_when_matched_empty(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-20 12:00:00', self::TIMEZONE));
        $manager = $this->phototextManager();
        $this->configurePhotoTextAgent($manager['user']->max_user_id);

        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Empty Apply Cafe', 'Борщ', 150);
        $dates = $this->weekWindowFrom('2026-08-20');

        $this->postJson('/api/food/phototext/schedule/apply', [
            'restaurant_id' => $fixture['restaurant']->id,
            'date_from' => $dates['date_from'],
            'date_to' => $dates['date_to'],
            'entries' => [
                ['name' => 'Несуществующее', 'dates' => [$dates['date_from']]],
            ],
        ], $this->photoTextHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('matched_count', 0)
            ->assertJsonPath('applied', false)
            ->assertJsonPath('categories_applied', []);

        $this->assertDatabaseCount('max_dish_availability_dates', 0);
    }

    /** Apply отклоняет прошлые даты в entries (окно пересекает сегодня). */
    public function test_apply_rejects_past_dates(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-20 12:00:00', self::TIMEZONE));
        $manager = $this->phototextManager();
        $this->configurePhotoTextAgent($manager['user']->max_user_id);

        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Past Cafe', 'Борщ', 150);
        // 19.08…25.08: вчера в entries, сегодня и дальше в окне — доменная проверка editable_from.
        $dates = $this->weekWindowFrom('2026-08-19');

        $this->postJson('/api/food/phototext/schedule/apply', [
            'restaurant_id' => $fixture['restaurant']->id,
            'date_from' => $dates['date_from'],
            'date_to' => $dates['date_to'],
            'entries' => [
                ['name' => 'Борщ', 'dates' => [$dates['date_from']]],
            ],
        ], $this->photoTextHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Нельзя изменять доступность на прошедшие даты.');

        $this->assertDatabaseCount('max_dish_availability_dates', 0);
    }

    /** Неверный span (date_to ≠ date_from + 6) возвращает 422. */
    public function test_rejects_when_date_range_is_not_exactly_seven_days(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-20 12:00:00', self::TIMEZONE));
        $manager = $this->phototextManager();
        $this->configurePhotoTextAgent($manager['user']->max_user_id);

        $fixture = FoodTestDataBuilder::createRestaurantWithDish('Span Cafe', 'Борщ', 150);

        $this->postJson('/api/food/phototext/schedule/match', [
            'restaurant_id' => $fixture['restaurant']->id,
            'date_from' => '2026-08-20',
            'date_to' => '2026-08-25',
            'entries' => [
                ['name' => 'Борщ', 'dates' => ['2026-08-20']],
            ],
        ], $this->photoTextHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_to']);
    }

    /**
     * @return array{user: MaxUser, headers: array<string, string>}
     */
    private function phototextManager(): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_026,
                'first_name' => 'PhotoTextScheduleManager',
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

    /**
     * Минимальное валидное по форме тело (ресторан может не существовать).
     *
     * @return array<string, mixed>
     */
    private function validPayload(int $restaurantId): array
    {
        return [
            'restaurant_id' => $restaurantId,
            'date_from' => '2026-08-20',
            'date_to' => '2026-08-26',
            'entries' => [
                ['name' => 'Борщ', 'dates' => ['2026-08-20']],
            ],
        ];
    }

    /**
     * Окно ровно 7 дней начиная с date_from.
     *
     * @return array{
     *     date_from: string,
     *     date_to: string,
     *     day_2: string,
     *     day_3: string
     * }
     */
    private function weekWindowFrom(string $dateFrom): array
    {
        $from = CarbonImmutable::createFromFormat('Y-m-d', $dateFrom)->startOfDay();

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $from->addDays(6)->toDateString(),
            'day_2' => $from->addDay()->toDateString(),
            'day_3' => $from->addDays(2)->toDateString(),
        ];
    }
}
