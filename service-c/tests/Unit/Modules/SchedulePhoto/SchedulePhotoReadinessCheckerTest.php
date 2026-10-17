<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Contracts\Max\MaxAiAccessServiceInterface;
use App\Contracts\Shared\ClockInterface;
use App\DTO\Max\AiAccessStatusDto;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoPhotoTextProbeInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoSidecarHealthProbeInterface;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoProbeResultDto;
use App\Modules\SchedulePhoto\Services\SchedulePhotoReadinessChecker;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Unit: агрегация AI + PhotoText + sidecar в readiness DTO.
 */
final class SchedulePhotoReadinessCheckerTest extends TestCase
{
    public function test_ok_when_all_checks_pass(): void
    {
        $result = $this->checker(
            ai: new AiAccessStatusDto(true, 1006, '2026-10-13T12:30:00+00:00'),
            hasMaxManager: true,
            photoText: new SchedulePhotoProbeResultDto(true, 'VPS PhotoText доступен.'),
            sidecar: new SchedulePhotoProbeResultDto(true, 'Sidecar LLM доступен.'),
        )->check('http://localhost:8083');

        $this->assertTrue($result->ok);
        $this->assertTrue($result->aiAccessOk);
        $this->assertTrue($result->photoTextOk);
        $this->assertTrue($result->sidecarOk);
        $this->assertSame([
            'ok' => true,
            'ai_access' => ['ok' => true, 'message' => 'AI-доступ активен.'],
            'phototext' => ['ok' => true, 'message' => 'VPS PhotoText доступен.'],
            'sidecar' => ['ok' => true, 'message' => 'Sidecar LLM доступен.'],
        ], $result->toArray());
    }

    public function test_fails_when_ai_disabled(): void
    {
        $result = $this->checker(
            ai: new AiAccessStatusDto(false, null, null),
            hasMaxManager: false,
            photoText: new SchedulePhotoProbeResultDto(true, 'VPS PhotoText доступен.'),
            sidecar: new SchedulePhotoProbeResultDto(true, 'Sidecar LLM доступен.'),
        )->check('http://localhost:8083');

        $this->assertFalse($result->ok);
        $this->assertFalse($result->aiAccessOk);
        $this->assertStringContainsString('Вкл. доступ AI', $result->aiAccessMessage);
        $this->assertTrue($result->photoTextOk);
        $this->assertTrue($result->sidecarOk);
    }

    public function test_fails_when_active_user_is_not_max_manager(): void
    {
        $result = $this->checker(
            ai: new AiAccessStatusDto(true, 1006, '2026-10-13T12:30:00+00:00'),
            hasMaxManager: false,
            photoText: new SchedulePhotoProbeResultDto(true, 'ok'),
            sidecar: new SchedulePhotoProbeResultDto(true, 'ok'),
        )->check('http://localhost:8083');

        $this->assertFalse($result->ok);
        $this->assertFalse($result->aiAccessOk);
    }

    public function test_fails_when_phototext_or_sidecar_fail(): void
    {
        $result = $this->checker(
            ai: new AiAccessStatusDto(true, 1006, '2026-10-13T12:30:00+00:00'),
            hasMaxManager: true,
            photoText: new SchedulePhotoProbeResultDto(false, 'VPS PhotoText: неверный токен (401).'),
            sidecar: new SchedulePhotoProbeResultDto(false, 'URL sidecar не настроен.'),
        )->check('http://dead.example');

        $this->assertFalse($result->ok);
        $this->assertTrue($result->aiAccessOk);
        $this->assertFalse($result->photoTextOk);
        $this->assertFalse($result->sidecarOk);
        $this->assertSame('VPS PhotoText: неверный токен (401).', $result->photoTextMessage);
        $this->assertSame('URL sidecar не настроен.', $result->sidecarMessage);
    }

    private function checker(
        AiAccessStatusDto $ai,
        bool $hasMaxManager,
        SchedulePhotoProbeResultDto $photoText,
        SchedulePhotoProbeResultDto $sidecar,
    ): SchedulePhotoReadinessChecker {
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

        $photoTextProbe = $this->createMock(SchedulePhotoPhotoTextProbeInterface::class);
        $photoTextProbe->expects($this->once())
            ->method('probe')
            ->with($this->isString())
            ->willReturn($photoText);

        $sidecarProbe = $this->createMock(SchedulePhotoSidecarHealthProbeInterface::class);
        $sidecarProbe->expects($this->once())
            ->method('checkHealth')
            ->willReturn($sidecar);

        return new SchedulePhotoReadinessChecker(
            $aiAccess,
            $admins,
            $clock,
            $photoTextProbe,
            $sidecarProbe,
        );
    }
}
