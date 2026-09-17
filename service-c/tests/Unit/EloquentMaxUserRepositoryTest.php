<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Max\MaxUserDeliveryRepositoryInterface;
use App\Contracts\Max\MaxUserManualOrderQueryRepositoryInterface;
use App\Models\Max\MaxUser;
use Tests\TestCase;

class EloquentMaxUserRepositoryTest extends TestCase
{
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
}
