<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Max\MaxUserDeliveryRepositoryInterface;
use App\Contracts\Max\MaxUserManualOrderQueryRepositoryInterface;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class EloquentMaxUserRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    /** Список id пользователей MAX с адресом исключает null и пустые. */
    public function test_list_max_user_ids_with_delivery_address_excludes_null_and_blank(): void
    {
        foreach ([
            [55_501, 'With address', 'ул. Примерная, 1'],
            [55_502, 'Null address', null],
            [55_503, 'Blank address', '   '],
        ] as [$maxUserId, $firstName, $deliveryAddress]) {
            MaxUser::query()->updateOrCreate(
                ['max_user_id' => $maxUserId],
                [
                    'first_name' => $firstName,
                    'delivery_address' => $deliveryAddress,
                ],
            );
        }

        $userIds = $this->app->make(MaxUserDeliveryRepositoryInterface::class)->listMaxUserIdsWithDeliveryAddress();

        $this->assertContains(55_501, $userIds);
        $this->assertNotContains(55_502, $userIds);
        $this->assertNotContains(55_503, $userIds);
    }

    /** Поиск по имени для PhotoText ограничивает выдачу тремя записями. */
    public function test_find_by_name_fields_substring_limits_to_three(): void
    {
        foreach ([55_601, 55_602, 55_603, 55_604] as $maxUserId) {
            MaxUser::query()->updateOrCreate(
                ['max_user_id' => $maxUserId],
                [
                    'first_name' => 'PhotoTextClient',
                    'last_name' => 'Match'.$maxUserId,
                    'username' => 'pt_user_'.$maxUserId,
                ],
            );
        }

        $users = $this->app
            ->make(MaxUserManualOrderQueryRepositoryInterface::class)
            ->findByNameFieldsSubstring('PhotoTextClient');

        $this->assertCount(3, $users);
        $this->assertSame(
            [55_601, 55_602, 55_603],
            array_map(static fn ($user): int => $user->maxUserId, $users),
        );
    }

    /** LIKE-метасимволы в q не матчат всех пользователей. */
    public function test_paginate_for_manual_orders_escapes_like_wildcards(): void
    {
        MaxUser::query()->create([
            'max_user_id' => 55_701,
            'first_name' => 'Alice',
            'username' => 'aliceplain',
        ]);
        MaxUser::query()->create([
            'max_user_id' => 55_702,
            'first_name' => 'Bob%',
            'username' => 'bobpercent',
        ]);
        MaxUser::query()->create([
            'max_user_id' => 55_703,
            'first_name' => 'Carol_underscore',
            'username' => 'carolplain',
        ]);

        $repository = $this->app->make(MaxUserManualOrderQueryRepositoryInterface::class);

        $percentResult = $repository->paginateForManualOrders('%', 50);
        $this->assertSame(1, $percentResult->total);
        $this->assertSame(55_702, $percentResult->items[0]->maxUserId);

        $underscoreResult = $repository->paginateForManualOrders('_', 50);
        $this->assertSame(1, $underscoreResult->total);
        $this->assertSame(55_703, $underscoreResult->items[0]->maxUserId);

        $substringResult = $repository->paginateForManualOrders('Alice', 50);
        $this->assertSame(1, $substringResult->total);
        $this->assertSame(55_701, $substringResult->items[0]->maxUserId);
    }

    /** paginateForManualOrders не ищет по delivery_address. */
    public function test_paginate_for_manual_orders_does_not_search_delivery_address(): void
    {
        MaxUser::query()->create([
            'max_user_id' => 55_711,
            'first_name' => 'OnlyAddress',
            'username' => 'only_addr',
            'delivery_address' => 'ул. СекретныйФрагментАдреса777',
        ]);

        $result = $this->app
            ->make(MaxUserManualOrderQueryRepositoryInterface::class)
            ->paginateForManualOrders('СекретныйФрагментАдреса777', 50);

        $this->assertSame(0, $result->total);
    }
}
