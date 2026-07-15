<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Max\MaxUserRepositoryInterface;
use App\Models\MaxUser;
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

        $userIds = $this->app->make(MaxUserRepositoryInterface::class)->listMaxUserIdsWithDeliveryAddress();

        $this->assertContains(55_501, $userIds);
        $this->assertNotContains(55_502, $userIds);
        $this->assertNotContains(55_503, $userIds);
    }
}
