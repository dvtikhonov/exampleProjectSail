<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Http\Mappers\MaxUserIdentityMapper;
use App\Models\Max\MaxUser;
use Mockery;
use Tests\TestCase;

class MaxUserIdentityMapperTest extends TestCase
{
    /** Маппер переносит max_user_id и активные роли администратора. */
    public function test_from_model_maps_max_user_id_and_admin_roles(): void
    {
        $user = new MaxUser([
            'max_user_id' => 42_001,
            'first_name' => 'Admin',
        ]);

        $adminRepository = Mockery::mock(FoodOrderAdminRepositoryInterface::class);
        $adminRepository
            ->shouldReceive('getActiveRoles')
            ->once()
            ->with(42_001)
            ->andReturn([
                FoodOrderAdminRole::AddressReviewer,
                FoodOrderAdminRole::MenuManager,
            ]);

        $identity = (new MaxUserIdentityMapper($adminRepository))->fromModel($user);

        $this->assertInstanceOf(MaxUserIdentity::class, $identity);
        $this->assertSame(42_001, $identity->maxUserId);
        $this->assertSame(
            [
                FoodOrderAdminRole::AddressReviewer,
                FoodOrderAdminRole::MenuManager,
            ],
            $identity->adminRoles,
        );
        $this->assertTrue($identity->hasAdminRole(FoodOrderAdminRole::MenuManager));
        $this->assertFalse($identity->hasAdminRole(FoodOrderAdminRole::MaxManager));
    }
}
