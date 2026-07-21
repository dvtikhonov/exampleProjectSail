<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Food\FoodOrderAdminRole;
use App\Models\FoodOrderAdmin;
use App\Models\MaxUser;
use App\Repositories\Food\EloquentFoodOrderAdminRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class EloquentFoodOrderAdminRepositoryTest extends TestCase
{
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private EloquentFoodOrderAdminRepository $repository;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
        $this->repository = new EloquentFoodOrderAdminRepository;
    }

    /** listActiveMaxUserIdsByRole возвращает только активных с нужной ролью. */
    public function test_list_active_max_user_ids_by_role_filters_active_role(): void
    {
        $this->createMaxUser(9001);
        $this->createMaxUser(9002);
        $this->createMaxUser(9003);
        $this->createMaxUser(9004);

        FoodOrderAdmin::query()->create([
            'max_user_id' => 9001,
            'role' => FoodOrderAdminRole::MaxManager,
            'is_active' => true,
        ]);
        FoodOrderAdmin::query()->create([
            'max_user_id' => 9002,
            'role' => FoodOrderAdminRole::MaxManager,
            'is_active' => false,
        ]);
        FoodOrderAdmin::query()->create([
            'max_user_id' => 9003,
            'role' => FoodOrderAdminRole::MenuManager,
            'is_active' => true,
        ]);
        FoodOrderAdmin::query()->create([
            'max_user_id' => 9004,
            'role' => FoodOrderAdminRole::MaxManager,
            'is_active' => true,
        ]);

        $this->assertSame(
            [9001, 9004],
            $this->repository->listActiveMaxUserIdsByRole(FoodOrderAdminRole::MaxManager),
        );
    }

    /** Создаёт пользователя MAX. */
    private function createMaxUser(int $maxUserId): MaxUser
    {
        return MaxUser::query()->create([
            'max_user_id' => $maxUserId,
            'first_name' => 'Admin'.$maxUserId,
        ]);
    }
}
