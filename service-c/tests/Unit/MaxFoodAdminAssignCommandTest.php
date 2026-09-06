<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\DTO\Max\MaxUserRecord;
use App\Enums\Food\Review\FoodOrderAdminRole;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MaxFoodAdminAssignCommandTest extends TestCase
{
    /** Команда назначает роль существующему пользователю MAX. */
    public function test_command_assigns_role_when_user_exists(): void
    {
        $maxUserId = 42_001;
        $userRepository = $this->createMock(MaxUserRepositoryInterface::class);
        $userRepository
            ->expects($this->once())
            ->method('findByMaxUserId')
            ->with($maxUserId)
            ->willReturn(new MaxUserRecord(
                maxUserId: $maxUserId,
                firstName: 'Test',
                lastName: null,
                username: null,
                languageCode: null,
                photoUrl: null,
                aiAccessUntil: null,
                customerCategoryId: null,
                deliveryAddress: null,
            ));

        $adminRepository = $this->createMock(FoodOrderAdminRepositoryInterface::class);
        $adminRepository
            ->expects($this->once())
            ->method('assignActiveRole')
            ->with($maxUserId, FoodOrderAdminRole::MaxManager)
            ->willReturn(17);

        $this->app->instance(MaxUserRepositoryInterface::class, $userRepository);
        $this->app->instance(FoodOrderAdminRepositoryInterface::class, $adminRepository);

        $exitCode = Artisan::call('max:food-admin:assign', [
            'max_user_id' => $maxUserId,
            'role' => FoodOrderAdminRole::MaxManager->value,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString(
            "Роль max_manager назначена пользователю max_user_id={$maxUserId}",
            $output,
        );
        $this->assertStringContainsString('запись #17', $output);
    }

    /** Команда завершается ошибкой, если пользователь MAX не найден. */
    public function test_command_fails_when_user_not_found(): void
    {
        $maxUserId = 42_002;
        $userRepository = $this->createMock(MaxUserRepositoryInterface::class);
        $userRepository
            ->expects($this->once())
            ->method('findByMaxUserId')
            ->with($maxUserId)
            ->willReturn(null);

        $adminRepository = $this->createMock(FoodOrderAdminRepositoryInterface::class);
        $adminRepository->expects($this->never())->method('assignActiveRole');

        $this->app->instance(MaxUserRepositoryInterface::class, $userRepository);
        $this->app->instance(FoodOrderAdminRepositoryInterface::class, $adminRepository);

        $exitCode = Artisan::call('max:food-admin:assign', [
            'max_user_id' => $maxUserId,
            'role' => FoodOrderAdminRole::MenuManager->value,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString(
            "Пользователь MAX с max_user_id={$maxUserId} не найден в max_users.",
            Artisan::output(),
        );
    }
}
