<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Contracts\Max\MaxAiAccessServiceInterface;
use App\Contracts\Shared\ClockInterface;
use App\DTO\Max\AiAccessStatusDto;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Modules\SchedulePhoto\Infrastructure\FoodMax\FoodMaxSchedulePhotoLocalAiAccess;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Unit: адаптер локального AI (статус Max + роль max_manager).
 */
final class FoodMaxSchedulePhotoLocalAiAccessTest extends TestCase
{
    public function test_granted_when_enabled_and_max_manager(): void
    {
        $this->assertTrue(
            $this->adapter(
                ai: new AiAccessStatusDto(true, 1006, '2026-10-13T12:30:00+00:00'),
                hasMaxManager: true,
            )->isGranted(),
        );
    }

    public function test_denied_when_ai_disabled(): void
    {
        $this->assertFalse(
            $this->adapter(
                ai: new AiAccessStatusDto(false, null, null),
                hasMaxManager: false,
            )->isGranted(),
        );
    }

    public function test_denied_when_active_user_is_not_max_manager(): void
    {
        $this->assertFalse(
            $this->adapter(
                ai: new AiAccessStatusDto(true, 1006, '2026-10-13T12:30:00+00:00'),
                hasMaxManager: false,
            )->isGranted(),
        );
    }

    public function test_denied_when_enabled_without_active_user(): void
    {
        $this->assertFalse(
            $this->adapter(
                ai: new AiAccessStatusDto(true, null, null),
                hasMaxManager: false,
            )->isGranted(),
        );
    }

    private function adapter(
        AiAccessStatusDto $ai,
        bool $hasMaxManager,
    ): FoodMaxSchedulePhotoLocalAiAccess {
        $now = new DateTimeImmutable('2026-10-13T12:00:00+00:00');

        $clock = $this->createMock(ClockInterface::class);
        $clock->expects($this->once())->method('now')->willReturn($now);

        $aiAccess = $this->createMock(MaxAiAccessServiceInterface::class);
        $aiAccess->expects($this->once())
            ->method('getStatus')
            ->with($now)
            ->willReturn($ai);

        $admins = $this->createMock(FoodOrderAdminRepositoryInterface::class);
        if ($ai->enabled && $ai->activeMaxUserId !== null) {
            $admins->expects($this->once())
                ->method('hasActiveRole')
                ->with($ai->activeMaxUserId, FoodOrderAdminRole::MaxManager)
                ->willReturn($hasMaxManager);
        } else {
            $admins->expects($this->never())->method('hasActiveRole');
        }

        return new FoodMaxSchedulePhotoLocalAiAccess($aiAccess, $admins, $clock);
    }
}
