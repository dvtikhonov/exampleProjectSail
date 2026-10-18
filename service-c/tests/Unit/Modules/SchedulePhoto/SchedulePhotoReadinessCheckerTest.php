<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoProbeResultDto;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoLocalAiAccessInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoTextProbeInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoSidecarHealthProbeInterface;
use App\Modules\SchedulePhoto\Application\UseCase\Readiness\SchedulePhotoReadinessChecker;
use PHPUnit\Framework\TestCase;

/**
 * Unit: агрегация AI + PhotoText + sidecar в readiness DTO.
 */
final class SchedulePhotoReadinessCheckerTest extends TestCase
{
    public function test_ok_when_all_checks_pass(): void
    {
        $result = $this->checker(
            localAiGranted: true,
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
            localAiGranted: false,
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
            localAiGranted: false,
            photoText: new SchedulePhotoProbeResultDto(true, 'ok'),
            sidecar: new SchedulePhotoProbeResultDto(true, 'ok'),
        )->check('http://localhost:8083');

        $this->assertFalse($result->ok);
        $this->assertFalse($result->aiAccessOk);
    }

    public function test_fails_when_phototext_or_sidecar_fail(): void
    {
        $result = $this->checker(
            localAiGranted: true,
            photoText: new SchedulePhotoProbeResultDto(false, 'Локальный PhotoText: неверный токен (401).', 401),
            sidecar: new SchedulePhotoProbeResultDto(false, 'URL sidecar не настроен.'),
        )->check('http://localhost:8083');

        $this->assertFalse($result->ok);
        $this->assertTrue($result->aiAccessOk);
        $this->assertFalse($result->photoTextOk);
        $this->assertFalse($result->sidecarOk);
        $this->assertSame('Локальный PhotoText: неверный токен (401).', $result->photoTextMessage);
        $this->assertSame('URL sidecar не настроен.', $result->sidecarMessage);
    }

    public function test_docker_host_still_uses_local_ai_flag(): void
    {
        $result = $this->checker(
            localAiGranted: false,
            photoText: new SchedulePhotoProbeResultDto(true, 'Локальный PhotoText доступен.', 200),
            sidecar: new SchedulePhotoProbeResultDto(true, 'Sidecar LLM доступен.'),
        )->check('http://host.docker.internal:8083');

        $this->assertFalse($result->ok);
        $this->assertFalse($result->aiAccessOk);
        $this->assertStringContainsString('Вкл. доступ AI', $result->aiAccessMessage);
        $this->assertTrue($result->photoTextOk);
    }

    public function test_remote_probe_200_confirms_ai_without_local_flag(): void
    {
        $result = $this->remoteChecker(
            photoText: new SchedulePhotoProbeResultDto(true, 'VPS PhotoText доступен.', 200),
            sidecar: new SchedulePhotoProbeResultDto(true, 'Sidecar LLM доступен.'),
        )->check('http://vps.example');

        $this->assertTrue($result->ok);
        $this->assertTrue($result->aiAccessOk);
        $this->assertSame('AI-доступ активен.', $result->aiAccessMessage);
        $this->assertTrue($result->photoTextOk);
        $this->assertTrue($result->sidecarOk);
    }

    public function test_remote_probe_200_with_phototext_error_still_confirms_ai(): void
    {
        $result = $this->remoteChecker(
            photoText: new SchedulePhotoProbeResultDto(false, 'VPS PhotoText: пустой список ресторанов.', 200),
            sidecar: new SchedulePhotoProbeResultDto(true, 'Sidecar LLM доступен.'),
        )->check('http://vps.example');

        $this->assertFalse($result->ok);
        $this->assertTrue($result->aiAccessOk);
        $this->assertFalse($result->photoTextOk);
        $this->assertSame('VPS PhotoText: пустой список ресторанов.', $result->photoTextMessage);
    }

    public function test_remote_probe_403_denies_only_ai_access(): void
    {
        $result = $this->remoteChecker(
            photoText: new SchedulePhotoProbeResultDto(false, 'Доступ AI к базе не разрешён.', 403),
            sidecar: new SchedulePhotoProbeResultDto(true, 'Sidecar LLM доступен.'),
        )->check('http://vps.example');

        $this->assertFalse($result->ok);
        $this->assertFalse($result->aiAccessOk);
        $this->assertSame('Доступ AI к базе не разрешён.', $result->aiAccessMessage);
        $this->assertTrue($result->photoTextOk);
        $this->assertSame('VPS PhotoText доступен.', $result->photoTextMessage);
        $this->assertTrue($result->sidecarOk);
    }

    public function test_remote_probe_403_unknown_message_confirms_neither_ai_nor_phototext(): void
    {
        $foreign = 'WAF blocked /internal stack trace';
        $result = $this->remoteChecker(
            photoText: new SchedulePhotoProbeResultDto(false, $foreign, 403),
            sidecar: new SchedulePhotoProbeResultDto(true, 'Sidecar LLM доступен.'),
        )->check('http://vps.example');

        $this->assertFalse($result->ok);
        $this->assertFalse($result->aiAccessOk);
        $this->assertSame('Флаг AI на выбранном сервере не проверен.', $result->aiAccessMessage);
        $this->assertFalse($result->photoTextOk);
        $this->assertSame($foreign, $result->photoTextMessage);
        $this->assertStringNotContainsString($foreign, $result->aiAccessMessage);
        $this->assertTrue($result->sidecarOk);
    }

    public function test_remote_probe_failure_does_not_confirm_ai_flag(): void
    {
        $result = $this->remoteChecker(
            photoText: new SchedulePhotoProbeResultDto(false, 'VPS PhotoText: неверный токен (401).', 401),
            sidecar: new SchedulePhotoProbeResultDto(true, 'Sidecar LLM доступен.'),
        )->check('http://vps.example');

        $this->assertFalse($result->ok);
        $this->assertFalse($result->aiAccessOk);
        $this->assertSame('Флаг AI на выбранном сервере не проверен.', $result->aiAccessMessage);
        $this->assertFalse($result->photoTextOk);
        $this->assertSame('VPS PhotoText: неверный токен (401).', $result->photoTextMessage);
        $this->assertTrue($result->sidecarOk);
    }

    public function test_remote_probe_redirect_does_not_confirm_ai_flag(): void
    {
        $result = $this->remoteChecker(
            photoText: new SchedulePhotoProbeResultDto(false, 'VPS PhotoText: HTTP 302.', 302),
            sidecar: new SchedulePhotoProbeResultDto(true, 'Sidecar LLM доступен.'),
        )->check('http://vps.example');

        $this->assertFalse($result->ok);
        $this->assertFalse($result->aiAccessOk);
        $this->assertSame('Флаг AI на выбранном сервере не проверен.', $result->aiAccessMessage);
        $this->assertFalse($result->photoTextOk);
        $this->assertSame('VPS PhotoText: HTTP 302.', $result->photoTextMessage);
        $this->assertTrue($result->sidecarOk);
    }

    public function test_remote_probe_non_json_does_not_confirm_ai_flag(): void
    {
        $result = $this->remoteChecker(
            photoText: new SchedulePhotoProbeResultDto(false, 'VPS PhotoText: пустой или неверный JSON.', 0),
            sidecar: new SchedulePhotoProbeResultDto(true, 'Sidecar LLM доступен.'),
        )->check('http://vps.example');

        $this->assertFalse($result->ok);
        $this->assertFalse($result->aiAccessOk);
        $this->assertSame('Флаг AI на выбранном сервере не проверен.', $result->aiAccessMessage);
        $this->assertFalse($result->photoTextOk);
        $this->assertSame('VPS PhotoText: пустой или неверный JSON.', $result->photoTextMessage);
        $this->assertTrue($result->sidecarOk);
    }

    private function checker(
        bool $localAiGranted,
        SchedulePhotoProbeResultDto $photoText,
        SchedulePhotoProbeResultDto $sidecar,
    ): SchedulePhotoReadinessChecker {
        $localAi = $this->createMock(SchedulePhotoLocalAiAccessInterface::class);
        $localAi->expects($this->once())
            ->method('isGranted')
            ->willReturn($localAiGranted);

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
            $localAi,
            $photoTextProbe,
            $sidecarProbe,
        );
    }

    private function remoteChecker(
        SchedulePhotoProbeResultDto $photoText,
        SchedulePhotoProbeResultDto $sidecar,
    ): SchedulePhotoReadinessChecker {
        $localAi = $this->createMock(SchedulePhotoLocalAiAccessInterface::class);
        $localAi->expects($this->never())->method('isGranted');

        $photoTextProbe = $this->createMock(SchedulePhotoPhotoTextProbeInterface::class);
        $photoTextProbe->expects($this->once())
            ->method('probe')
            ->with('http://vps.example')
            ->willReturn($photoText);

        $sidecarProbe = $this->createMock(SchedulePhotoSidecarHealthProbeInterface::class);
        $sidecarProbe->expects($this->once())
            ->method('checkHealth')
            ->willReturn($sidecar);

        return new SchedulePhotoReadinessChecker(
            $localAi,
            $photoTextProbe,
            $sidecarProbe,
        );
    }
}
