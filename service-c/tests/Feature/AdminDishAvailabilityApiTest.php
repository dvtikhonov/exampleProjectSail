<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Food\FoodOrderAdminRole;
use App\Models\DishAvailabilityDate;
use App\Models\MaxUser;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class AdminDishAvailabilityApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string TIMEZONE = 'Europe/Moscow';

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    public function test_dish_availability_endpoints_return_unauthorized_without_token(): void
    {
        $this->getJson('/api/food/admin/dish-availability-schedule?restaurant_id=1&category_id=1')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->putJson('/api/food/admin/dish-availability-schedule', [
            'restaurant_id' => 1,
            'category_id' => 1,
            'changes' => [],
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_dish_availability_endpoints_return_forbidden_without_menu_manager_role(): void
    {
        $auth = $this->authenticateMaxUser();

        $this->getJson('/api/food/admin/dish-availability-schedule?restaurant_id=1&category_id=1', $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');

        $this->putJson('/api/food/admin/dish-availability-schedule', [
            'restaurant_id' => 1,
            'category_id' => 1,
            'changes' => [],
        ], $auth['headers'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_get_requires_restaurant_and_category_filters(): void
    {
        $auth = $this->menuManagerAuth();

        $this->getJson('/api/food/admin/dish-availability-schedule', $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['restaurant_id', 'category_id']);
    }

    public function test_menu_manager_can_get_schedule_with_required_filters(): void
    {
        $dates = $this->scheduleDates();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(dishName: 'Борщ');
        $auth = $this->menuManagerAuth();

        DishAvailabilityDate::query()->create([
            'dish_id' => $fixture['dish']->id,
            'available_date' => $dates['future'],
        ]);

        $this->getJson(
            '/api/food/admin/dish-availability-schedule'
            .'?restaurant_id='.$fixture['restaurant']->id
            .'&category_id='.$fixture['category']->id,
            $auth['headers'],
        )
            ->assertOk()
            ->assertJsonPath('editable_from', $dates['editable_from'])
            ->assertJsonCount(1, 'dishes')
            ->assertJsonPath('dishes.0.name', 'Борщ')
            ->assertJsonPath('schedule.'.$fixture['dish']->id, [$dates['future']])
            ->assertJsonPath('dates.0', $dates['editable_from'])
            ->assertJsonCount(30, 'dates');
    }

    public function test_get_clamps_past_date_from_to_editable_range(): void
    {
        $dates = $this->scheduleDates();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();

        $response = $this->getJson(
            '/api/food/admin/dish-availability-schedule'
            .'?restaurant_id='.$fixture['restaurant']->id
            .'&category_id='.$fixture['category']->id
            .'&date_from='.$dates['range_from']
            .'&date_to='.$dates['future'],
            $auth['headers'],
        )
            ->assertOk()
            ->assertJsonPath('dates.0', $dates['editable_from']);

        $this->assertNotContains($dates['today'], $response->json('dates'));
        $this->assertNotContains($dates['yesterday'], $response->json('dates'));
    }

    public function test_put_adds_and_removes_future_availability_dates(): void
    {
        $dates = $this->scheduleDates();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();
        $dishId = $fixture['dish']->id;

        $this->putJson('/api/food/admin/dish-availability-schedule', [
            'restaurant_id' => $fixture['restaurant']->id,
            'category_id' => $fixture['category']->id,
            'date_from' => $dates['editable_from'],
            'date_to' => $dates['range_to'],
            'changes' => [
                [
                    'dish_id' => $dishId,
                    'dates' => [$dates['future'], $dates['future_plus_one']],
                ],
            ],
        ], $auth['headers'])
            ->assertOk()
            ->assertJsonPath('message', 'График доступности сохранён.');

        $this->assertDatabaseHas('max_dish_availability_dates', [
            'dish_id' => $dishId,
            'available_date' => $dates['future'],
        ]);
        $this->assertDatabaseHas('max_dish_availability_dates', [
            'dish_id' => $dishId,
            'available_date' => $dates['future_plus_one'],
        ]);

        $this->putJson('/api/food/admin/dish-availability-schedule', [
            'restaurant_id' => $fixture['restaurant']->id,
            'category_id' => $fixture['category']->id,
            'date_from' => $dates['editable_from'],
            'date_to' => $dates['range_to'],
            'changes' => [
                [
                    'dish_id' => $dishId,
                    'dates' => [$dates['future']],
                ],
            ],
        ], $auth['headers'])
            ->assertOk();

        $this->assertDatabaseHas('max_dish_availability_dates', [
            'dish_id' => $dishId,
            'available_date' => $dates['future'],
        ]);
        $this->assertDatabaseMissing('max_dish_availability_dates', [
            'dish_id' => $dishId,
            'available_date' => $dates['future_plus_one'],
        ]);
    }

    public function test_put_rejects_changes_for_today_and_yesterday(): void
    {
        $dates = $this->scheduleDates();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();
        $dishId = $fixture['dish']->id;

        $payload = [
            'restaurant_id' => $fixture['restaurant']->id,
            'category_id' => $fixture['category']->id,
            'date_from' => $dates['range_from'],
            'date_to' => $dates['range_to'],
            'changes' => [
                [
                    'dish_id' => $dishId,
                    'dates' => [$dates['yesterday']],
                ],
            ],
        ];

        $this->putJson('/api/food/admin/dish-availability-schedule', $payload, $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Нельзя изменять доступность на сегодня или прошедшие даты.');

        $payload['changes'][0]['dates'] = [$dates['today']];

        $this->putJson('/api/food/admin/dish-availability-schedule', $payload, $auth['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Нельзя изменять доступность на сегодня или прошедшие даты.');
    }

    public function test_put_does_not_create_duplicate_dish_date_rows(): void
    {
        $dates = $this->scheduleDates();
        $fixture = FoodTestDataBuilder::createRestaurantWithDish();
        $auth = $this->menuManagerAuth();
        $dishId = $fixture['dish']->id;

        $payload = [
            'restaurant_id' => $fixture['restaurant']->id,
            'category_id' => $fixture['category']->id,
            'date_from' => $dates['editable_from'],
            'date_to' => $dates['range_to'],
            'changes' => [
                [
                    'dish_id' => $dishId,
                    'dates' => [$dates['future']],
                ],
            ],
        ];

        $this->putJson('/api/food/admin/dish-availability-schedule', $payload, $auth['headers'])
            ->assertOk();

        $this->putJson('/api/food/admin/dish-availability-schedule', $payload, $auth['headers'])
            ->assertOk();

        $this->assertSame(
            1,
            DishAvailabilityDate::query()
                ->where('dish_id', $dishId)
                ->whereDate('available_date', $dates['future'])
                ->count(),
        );
    }

    /**
     * @return array{
     *     today: string,
     *     yesterday: string,
     *     editable_from: string,
     *     future: string,
     *     future_plus_one: string,
     *     range_from: string,
     *     range_to: string,
     * }
     */
    private function scheduleDates(): array
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $editableFrom = $today->addDay();

        return [
            'today' => $today->toDateString(),
            'yesterday' => $today->subDay()->toDateString(),
            'editable_from' => $editableFrom->toDateString(),
            'future' => $editableFrom->addDays(4)->toDateString(),
            'future_plus_one' => $editableFrom->addDays(5)->toDateString(),
            'range_from' => $today->subDays(2)->toDateString(),
            'range_to' => $today->addDays(30)->toDateString(),
        ];
    }

    /**
     * @return array{user: MaxUser, token: string, headers: array<string, string>}
     */
    private function menuManagerAuth(): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => 10_010,
                'first_name' => 'MenuManager',
            ])),
            FoodOrderAdminRole::MenuManager,
        );
    }
}
