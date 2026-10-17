<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\DTO\Food\PhotoText\PhotoTextScheduleResultDto;
use App\Exceptions\Food\FoodDomainException;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoAliasDictionaryInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoAliasesReaderInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoAliasesWriterInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoCatalogScopeBuilderInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoOcrOrchestratorInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoOcrQueueInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoPhotoStoreInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoReadinessCheckerInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoRunRepositoryInterface;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoOcrCallbackDto;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoOcrStartDto;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoReadinessResultDto;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoStartRunDto;
use App\Modules\SchedulePhoto\Enums\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Services\SchedulePhotoRunService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchedulePhotoRunServiceOcrTest extends TestCase
{
    #[Test]
    public function start_creates_run_and_dispatches_queue(): void
    {
        $runIdHolder = new \stdClass;
        $runIdHolder->id = null;

        $readiness = $this->createMock(SchedulePhotoReadinessCheckerInterface::class);
        $readiness->method('check')->willReturn($this->okReadiness());

        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->expects($this->once())->method('create')->willReturnCallback(
            function (array $attrs) use ($runIdHolder): SchedulePhotoRunRecord {
                $runIdHolder->id = $attrs['id'];

                return $this->record($attrs['id'], SchedulePhotoRunStatus::Pending, '/tmp/photo.jpg');
            },
        );
        $runs->method('findById')->willReturnCallback(
            fn (string $id): SchedulePhotoRunRecord => $this->record($id, SchedulePhotoRunStatus::Pending, '/tmp/photo.jpg'),
        );

        $queue = $this->createMock(SchedulePhotoOcrQueueInterface::class);
        $queue->expects($this->once())->method('dispatch');

        $photoStore = $this->createMock(SchedulePhotoPhotoStoreInterface::class);
        $photoStore->method('storeFromLocalPath')->willReturn('/tmp/photo.jpg');

        $catalog = $this->createMock(SchedulePhotoCatalogScopeBuilderInterface::class);
        $catalog->expects($this->once())->method('build')->with(
            'http://localhost:8083',
            1,
            [2],
        )->willReturn([
            ['id' => 1, 'name' => 'Пюре', 'category_id' => 2, 'category_name' => 'Гарнир'],
        ]);

        $service = $this->service(
            readiness: $readiness,
            runs: $runs,
            ocrQueue: $queue,
            photoStore: $photoStore,
            catalogScope: $catalog,
        );

        $tmp = tempnam(sys_get_temp_dir(), 'sph');
        $this->assertNotFalse($tmp);
        file_put_contents($tmp, 'img');

        try {
            $run = $service->start(new SchedulePhotoStartRunDto(
                baseUrl: 'http://localhost:8083',
                dateFrom: '2026-08-13',
                restaurantId: 1,
                categoryIds: [2],
                photoAbsolutePath: $tmp,
                createdByMaxUserId: 42,
            ));
            $this->assertSame(SchedulePhotoRunStatus::Pending, $run->status);
            $this->assertSame('2026-08-19', $run->dateTo);
        } finally {
            @unlink($tmp);
        }
    }

    #[Test]
    public function perform_queued_ocr_handshakes_sidecar(): void
    {
        $runId = '11111111-1111-4111-8111-111111111111';

        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('findById')->willReturn(
            $this->record($runId, SchedulePhotoRunStatus::Pending, '/tmp/photo.jpg'),
        );
        $runs->expects($this->atLeastOnce())->method('update')->willReturnCallback(
            fn (string $id, array $attrs): SchedulePhotoRunRecord => $this->record(
                $id,
                $attrs['status'] instanceof SchedulePhotoRunStatus
                    ? $attrs['status']
                    : SchedulePhotoRunStatus::from((string) $attrs['status']),
                '/tmp/photo.jpg',
            ),
        );

        $orchestrator = $this->createMock(SchedulePhotoOcrOrchestratorInterface::class);
        $orchestrator->expects($this->once())->method('start')->with(
            $this->callback(function (SchedulePhotoOcrStartDto $dto) use ($runId): bool {
                $payload = $dto->toArray();

                return $dto->runId === $runId
                    && $dto->photoKey === 'schedule-photo/photo.jpg'
                    && $dto->aliasesMarkdown !== ''
                    && ($payload['photo_key'] ?? null) === 'schedule-photo/photo.jpg'
                    && ! array_key_exists('photo_base64', $payload)
                    && ! array_key_exists('photo_path', $payload)
                    && ! array_key_exists('capture_secret', $payload);
            }),
        );

        $photoStore = $this->createMock(SchedulePhotoPhotoStoreInterface::class);
        $photoStore->expects($this->once())
            ->method('relativeKey')
            ->with('/tmp/photo.jpg')
            ->willReturn('schedule-photo/photo.jpg');

        $aliases = $this->createMock(SchedulePhotoAliasesReaderInterface::class);
        $aliases->method('readMarkdown')->willReturn('| пюре | Картофельное пюре |');

        $catalog = $this->createMock(SchedulePhotoCatalogScopeBuilderInterface::class);
        $catalog->expects($this->once())->method('build')->with(
            'http://localhost:8083',
            1,
            [2],
        )->willReturn([
            ['id' => 1, 'name' => 'Пюре', 'category_id' => 2, 'category_name' => 'Гарнир'],
        ]);

        $service = $this->service(
            runs: $runs,
            ocrOrchestrator: $orchestrator,
            photoStore: $photoStore,
            aliasesReader: $aliases,
            catalogScope: $catalog,
        );

        $service->performQueuedOcr($runId);
    }

    #[Test]
    public function complete_ocr_runs_matcher_and_sets_matched(): void
    {
        $runId = '22222222-2222-4222-8222-222222222222';

        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('findById')->willReturn(
            $this->record($runId, SchedulePhotoRunStatus::OcrRunning, '/tmp/photo.jpg'),
        );
        $runs->expects($this->once())->method('update')->willReturnCallback(
            function (string $id, array $attrs): SchedulePhotoRunRecord {
                $this->assertSame(SchedulePhotoRunStatus::Matched, $attrs['status']);
                $this->assertIsArray($attrs['match_result']);
                $this->assertSame(1, $attrs['match_result']['matched_count'] ?? null);

                return $this->record($id, SchedulePhotoRunStatus::Matched, '/tmp/photo.jpg', $attrs['match_result']);
            },
        );

        $photoText = $this->createMock(SchedulePhotoPhotoTextClientInterface::class);
        $photoText->expects($this->once())->method('match')->with(
            'http://localhost:8083',
            1,
            [2],
            '2026-08-13',
            '2026-08-19',
            $this->callback(function (array $entries): bool {
                $this->assertCount(1, $entries);
                $this->assertSame('Пюре', $entries[0]->name);
                $this->assertSame(['2026-08-13'], $entries[0]->dates);

                return true;
            }),
        )->willReturn(new PhotoTextScheduleResultDto(
            matchedCount: 1,
            matched: [],
            issues: [],
            dateFrom: '2026-08-13',
            dateTo: '2026-08-19',
        ));

        $service = $this->service(runs: $runs, photoText: $photoText);

        $result = $service->completeOcr(new SchedulePhotoOcrCallbackDto(
            runId: $runId,
            entries: [['raw_title' => 'Пюре', 'dates' => ['2026-08-13']]],
            discarded: [['raw_title' => 'хз', 'reason' => 'dish_not_found']],
        ));

        $this->assertSame(SchedulePhotoRunStatus::Matched, $result->status);
    }

    #[Test]
    public function complete_ocr_error_marks_failed(): void
    {
        $runId = '33333333-3333-4333-8333-333333333333';

        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('findById')->willReturn(
            $this->record($runId, SchedulePhotoRunStatus::OcrRunning, '/tmp/photo.jpg'),
        );
        $runs->expects($this->once())->method('update')->willReturnCallback(
            function (string $id, array $attrs): SchedulePhotoRunRecord {
                $this->assertSame(SchedulePhotoRunStatus::Failed, $attrs['status']);
                $this->assertSame('boom', $attrs['error_message']);

                return $this->record($id, SchedulePhotoRunStatus::Failed, '/tmp/photo.jpg');
            },
        );

        $service = $this->service(runs: $runs);

        $service->completeOcr(new SchedulePhotoOcrCallbackDto(
            runId: $runId,
            error: 'boom',
        ));
    }

    #[Test]
    public function start_stops_when_readiness_fails(): void
    {
        $readiness = $this->createMock(SchedulePhotoReadinessCheckerInterface::class);
        $readiness->method('check')->willReturn(new SchedulePhotoReadinessResultDto(
            ok: false,
            aiAccessOk: false,
            aiAccessMessage: 'AI off',
            photoTextOk: true,
            photoTextMessage: 'ok',
            sidecarOk: true,
            sidecarMessage: 'ok',
        ));

        $service = $this->service(readiness: $readiness);

        $this->expectException(FoodDomainException::class);
        $service->start(new SchedulePhotoStartRunDto(
            baseUrl: 'http://localhost:8083',
            dateFrom: '2026-08-13',
            restaurantId: 1,
            categoryIds: [2],
            photoAbsolutePath: '/tmp/x',
            createdByMaxUserId: 1,
        ));
    }

    #[Test]
    public function apply_writes_via_applier_when_matched_count_positive(): void
    {
        $runId = '44444444-4444-4444-8444-444444444444';
        $stored = [
            'matched_count' => 1,
            'matched' => [[
                'raw_title' => 'пюре',
                'dish_id' => 10,
                'dish_name' => 'Картофельное пюре',
                'category_id' => 2,
                'category_name' => 'Гарнир',
                'dates' => ['2026-08-13'],
            ]],
            'issues' => [[
                'code' => 'dish_not_found',
                'message' => 'не найдено',
                'raw_title' => 'хз',
                'dates' => ['2026-08-14'],
            ]],
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'applied' => false,
            'categories_applied' => [],
        ];

        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('findById')->willReturn(
            $this->record($runId, SchedulePhotoRunStatus::Matched, '/tmp/photo.jpg', $stored),
        );
        $runs->expects($this->once())->method('update')->willReturnCallback(
            function (string $id, array $attrs): SchedulePhotoRunRecord {
                $this->assertSame(SchedulePhotoRunStatus::Applied, $attrs['status']);
                $this->assertTrue($attrs['match_result']['applied'] ?? false);
                $this->assertSame(1, $attrs['match_result']['matched_count'] ?? null);

                return $this->record($id, SchedulePhotoRunStatus::Applied, '/tmp/photo.jpg', $attrs['match_result']);
            },
        );

        $photoText = $this->createMock(SchedulePhotoPhotoTextClientInterface::class);
        $photoText->expects($this->once())->method('apply')->with(
            'http://localhost:8083',
            1,
            [2],
            '2026-08-13',
            '2026-08-19',
            $this->callback(function (array $entries): bool {
                $this->assertCount(2, $entries);
                $this->assertSame('Картофельное пюре', $entries[0]->name);
                $this->assertSame(['2026-08-13'], $entries[0]->dates);
                $this->assertSame('хз', $entries[1]->name);

                return true;
            }),
        )->willReturn(new PhotoTextScheduleResultDto(
            matchedCount: 1,
            matched: [],
            issues: [],
            dateFrom: '2026-08-13',
            dateTo: '2026-08-19',
            applied: true,
            categoriesApplied: [2],
        ));

        $service = $this->service(runs: $runs, photoText: $photoText);
        $result = $service->apply($runId, 42);

        $this->assertSame(SchedulePhotoRunStatus::Applied, $result->status);
    }

    #[Test]
    public function apply_stops_when_matched_count_is_zero(): void
    {
        $runId = '55555555-5555-4555-8555-555555555555';
        $stored = [
            'matched_count' => 0,
            'matched' => [],
            'issues' => [[
                'code' => 'dish_not_found',
                'message' => 'не найдено',
                'raw_title' => 'хз',
                'dates' => ['2026-08-14'],
            ]],
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'applied' => false,
            'categories_applied' => [],
        ];

        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('findById')->willReturn(
            $this->record($runId, SchedulePhotoRunStatus::Matched, '/tmp/photo.jpg', $stored),
        );
        $runs->expects($this->never())->method('update');

        $photoText = $this->createMock(SchedulePhotoPhotoTextClientInterface::class);
        $photoText->expects($this->never())->method('apply');

        $service = $this->service(runs: $runs, photoText: $photoText);

        try {
            $service->apply($runId, 42);
            $this->fail('Expected FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(422, $exception->statusCode());
            $this->assertStringContainsString('matched_count = 0', $exception->getMessage());
        }
    }

    #[Test]
    public function apply_rejects_when_status_is_not_matched(): void
    {
        $runId = '66666666-6666-4666-8666-666666666666';

        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('findById')->willReturn(
            $this->record($runId, SchedulePhotoRunStatus::OcrRunning, '/tmp/photo.jpg'),
        );

        $photoText = $this->createMock(SchedulePhotoPhotoTextClientInterface::class);
        $photoText->expects($this->never())->method('apply');

        $service = $this->service(runs: $runs, photoText: $photoText);

        $this->expectException(FoodDomainException::class);
        $service->apply($runId, 42);
    }

    #[Test]
    public function apply_hides_foreign_run_as_not_found_without_writing(): void
    {
        $runId = '77777777-7777-4777-8777-777777777777';
        $stored = [
            'matched_count' => 1,
            'matched' => [[
                'raw_title' => 'пюре',
                'dish_id' => 10,
                'dish_name' => 'Картофельное пюре',
                'category_id' => 2,
                'category_name' => 'Гарнир',
                'dates' => ['2026-08-13'],
            ]],
            'issues' => [],
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'applied' => false,
            'categories_applied' => [],
        ];

        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('findById')->willReturn(
            $this->record($runId, SchedulePhotoRunStatus::Matched, '/tmp/photo.jpg', $stored),
        );
        $runs->expects($this->never())->method('update');

        $photoText = $this->createMock(SchedulePhotoPhotoTextClientInterface::class);
        $photoText->expects($this->never())->method('apply');

        $service = $this->service(runs: $runs, photoText: $photoText);

        try {
            $service->apply($runId, 99);
            $this->fail('Expected FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(404, $exception->statusCode());
            $this->assertSame('Прогон SchedulePhoto не найден.', $exception->getMessage());
        }
    }

    private function service(
        ?SchedulePhotoReadinessCheckerInterface $readiness = null,
        ?SchedulePhotoRunRepositoryInterface $runs = null,
        ?SchedulePhotoOcrQueueInterface $ocrQueue = null,
        ?SchedulePhotoOcrOrchestratorInterface $ocrOrchestrator = null,
        ?SchedulePhotoPhotoStoreInterface $photoStore = null,
        ?SchedulePhotoAliasesReaderInterface $aliasesReader = null,
        ?SchedulePhotoAliasDictionaryInterface $aliasDictionary = null,
        ?SchedulePhotoAliasesWriterInterface $aliasesWriter = null,
        ?SchedulePhotoCatalogScopeBuilderInterface $catalogScope = null,
        ?SchedulePhotoPhotoTextClientInterface $photoText = null,
    ): SchedulePhotoRunService {
        return new SchedulePhotoRunService(
            readiness: $readiness ?? $this->createMock(SchedulePhotoReadinessCheckerInterface::class),
            runs: $runs ?? $this->createMock(SchedulePhotoRunRepositoryInterface::class),
            ocrQueue: $ocrQueue ?? $this->createMock(SchedulePhotoOcrQueueInterface::class),
            ocrOrchestrator: $ocrOrchestrator ?? $this->createMock(SchedulePhotoOcrOrchestratorInterface::class),
            photoStore: $photoStore ?? $this->createMock(SchedulePhotoPhotoStoreInterface::class),
            aliasesReader: $aliasesReader ?? $this->createMock(SchedulePhotoAliasesReaderInterface::class),
            aliasDictionary: $aliasDictionary ?? $this->createMock(SchedulePhotoAliasDictionaryInterface::class),
            aliasesWriter: $aliasesWriter ?? $this->createMock(SchedulePhotoAliasesWriterInterface::class),
            catalogScope: $catalogScope ?? $this->createMock(SchedulePhotoCatalogScopeBuilderInterface::class),
            photoText: $photoText ?? $this->createMock(SchedulePhotoPhotoTextClientInterface::class),
        );
    }

    private function okReadiness(): SchedulePhotoReadinessResultDto
    {
        return new SchedulePhotoReadinessResultDto(
            ok: true,
            aiAccessOk: true,
            aiAccessMessage: 'ok',
            photoTextOk: true,
            photoTextMessage: 'ok',
            sidecarOk: true,
            sidecarMessage: 'ok',
        );
    }

    /**
     * @param  array<string, mixed>|null  $matchResult
     */
    private function record(
        string $id,
        SchedulePhotoRunStatus $status,
        ?string $photoPath,
        ?array $matchResult = null,
    ): SchedulePhotoRunRecord {
        return new SchedulePhotoRunRecord(
            id: $id,
            status: $status,
            baseUrl: 'http://localhost:8083',
            dateFrom: '2026-08-13',
            dateTo: '2026-08-19',
            restaurantId: 1,
            categoryIds: [2],
            createdByMaxUserId: 42,
            photoPath: $photoPath,
            matchResult: $matchResult,
            discarded: [],
        );
    }
}
