<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasesReaderInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoCatalogScopeBuilderInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoIdGeneratorInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoOcrOrchestratorInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoOcrQueueInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoStoreInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoReadinessCheckerInterface;
use App\Modules\SchedulePhoto\Domain\Repository\SchedulePhotoRunRepositoryInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoCatalogScopeDishDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoDiscardedRowDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoOcrCallbackDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoOcrStartDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoReadinessResultDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRunCreateDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRunPatchDto;
use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleIssueDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleMatchedDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleResultDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoStartRunDto;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoMatchIssueCode;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;
use App\Modules\SchedulePhoto\Application\UseCase\Ocr\SchedulePhotoOcrCompleter;
use App\Modules\SchedulePhoto\Application\UseCase\Ocr\SchedulePhotoOcrJobHandler;
use App\Modules\SchedulePhoto\Application\UseCase\Start\SchedulePhotoRunAccess;
use App\Modules\SchedulePhoto\Application\UseCase\Apply\SchedulePhotoRunApplier;
use App\Modules\SchedulePhoto\Application\UseCase\Start\SchedulePhotoRunStarter;
use App\Modules\SchedulePhoto\Domain\Service\SchedulePhotoStoredMatchEntries;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchedulePhotoRunServiceOcrTest extends TestCase
{
    #[Test]
    public function start_creates_run_and_dispatches_queue(): void
    {
        $runId = '11111111-1111-4111-8111-111111111111';

        $readiness = $this->createMock(SchedulePhotoReadinessCheckerInterface::class);
        $readiness->method('check')->willReturn($this->okReadiness());

        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->expects($this->once())->method('create')->willReturnCallback(
            function (SchedulePhotoRunCreateDto $dto) use ($runId): SchedulePhotoRunRecord {
                $this->assertSame($runId, $dto->id);

                return $this->record($dto->id, SchedulePhotoRunStatus::Pending, '/tmp/photo.jpg');
            },
        );
        $runs->method('findById')->willReturnCallback(
            fn (string $id): SchedulePhotoRunRecord => $this->record($id, SchedulePhotoRunStatus::Pending, '/tmp/photo.jpg'),
        );

        $queue = $this->createMock(SchedulePhotoOcrQueueInterface::class);
        $queue->expects($this->once())->method('dispatch')->with($runId);

        $photoStore = $this->createMock(SchedulePhotoPhotoStoreInterface::class);
        $photoStore->expects($this->once())->method('storeFromLocalPath')->willReturnCallback(
            function (string $id) use ($runId): string {
                $this->assertSame($runId, $id);

                return '/tmp/photo.jpg';
            },
        );
        $photoStore->expects($this->never())->method('delete');

        $idGenerator = $this->createMock(SchedulePhotoIdGeneratorInterface::class);
        $idGenerator->expects($this->once())->method('generate')->willReturn($runId);

        $catalog = $this->createMock(SchedulePhotoCatalogScopeBuilderInterface::class);
        $catalog->expects($this->once())->method('build')->with(
            'http://localhost:8083',
            1,
            [2],
        )->willReturn([
            new SchedulePhotoCatalogScopeDishDto(
                id: 1,
                name: 'Пюре',
                categoryId: 2,
                categoryName: 'Гарнир',
            ),
        ]);

        $service = $this->service(
            readiness: $readiness,
            runs: $runs,
            ocrQueue: $queue,
            photoStore: $photoStore,
            idGenerator: $idGenerator,
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
            $this->assertSame($runId, $run->id);
            $this->assertSame(SchedulePhotoRunStatus::Pending, $run->status);
            $this->assertSame('2026-08-19', $run->dateTo);
        } finally {
            @unlink($tmp);
        }
    }

    #[Test]
    public function start_deletes_photo_when_create_does_not_persist_a_row(): void
    {
        $readiness = $this->createMock(SchedulePhotoReadinessCheckerInterface::class);
        $readiness->method('check')->willReturn($this->okReadiness());

        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->expects($this->once())->method('create')->willThrowException(new \RuntimeException('db down'));
        $runs->expects($this->once())->method('findById')->willReturn(null);
        $runs->expects($this->never())->method('update');

        $queue = $this->createMock(SchedulePhotoOcrQueueInterface::class);
        $queue->expects($this->never())->method('dispatch');

        $photoStore = $this->createMock(SchedulePhotoPhotoStoreInterface::class);
        $photoStore->method('storeFromLocalPath')->willReturn('/storage/schedule-photo/run.jpg');
        $photoStore->expects($this->once())->method('delete')->with('/storage/schedule-photo/run.jpg');

        $service = $this->service(
            readiness: $readiness,
            runs: $runs,
            ocrQueue: $queue,
            photoStore: $photoStore,
            catalogScope: $this->catalogScope(),
        );

        try {
            $service->start($this->startDto());
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $exception) {
            $this->assertSame('db down', $exception->getMessage());
        }
    }

    #[Test]
    public function start_reports_create_error_when_photo_cleanup_fails(): void
    {
        $readiness = $this->createMock(SchedulePhotoReadinessCheckerInterface::class);
        $readiness->method('check')->willReturn($this->okReadiness());

        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('create')->willThrowException(new \RuntimeException('db down'));
        $runs->method('findById')->willReturn(null);

        $photoStore = $this->createMock(SchedulePhotoPhotoStoreInterface::class);
        $photoStore->method('storeFromLocalPath')->willReturn('/storage/photo.jpg');
        $photoStore->method('delete')->willThrowException(new \RuntimeException('unlink failed'));

        $service = $this->service(
            readiness: $readiness,
            runs: $runs,
            photoStore: $photoStore,
            catalogScope: $this->catalogScope(),
        );

        try {
            $service->start($this->startDto());
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $exception) {
            $this->assertSame('db down', $exception->getMessage());
        }
    }

    #[Test]
    public function start_keeps_photo_when_create_throws_but_the_row_exists(): void
    {
        $readiness = $this->createMock(SchedulePhotoReadinessCheckerInterface::class);
        $readiness->method('check')->willReturn($this->okReadiness());

        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('create')->willThrowException(new \RuntimeException('mapper down'));
        $runs->method('findById')->willReturn(
            $this->record('11111111-1111-4111-8111-111111111111', SchedulePhotoRunStatus::Pending, '/storage/photo.jpg'),
        );

        $queue = $this->createMock(SchedulePhotoOcrQueueInterface::class);
        $queue->expects($this->never())->method('dispatch');

        $photoStore = $this->createMock(SchedulePhotoPhotoStoreInterface::class);
        $photoStore->method('storeFromLocalPath')->willReturn('/storage/photo.jpg');
        $photoStore->expects($this->never())->method('delete');

        $service = $this->service(
            readiness: $readiness,
            runs: $runs,
            ocrQueue: $queue,
            photoStore: $photoStore,
            catalogScope: $this->catalogScope(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mapper down');
        $service->start($this->startDto());
    }

    #[Test]
    public function start_keeps_photo_when_run_lookup_fails_after_create(): void
    {
        $readiness = $this->createMock(SchedulePhotoReadinessCheckerInterface::class);
        $readiness->method('check')->willReturn($this->okReadiness());

        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('create')->willThrowException(new \RuntimeException('db down'));
        $runs->method('findById')->willThrowException(new \RuntimeException('db still down'));

        $photoStore = $this->createMock(SchedulePhotoPhotoStoreInterface::class);
        $photoStore->method('storeFromLocalPath')->willReturn('/storage/photo.jpg');
        $photoStore->expects($this->never())->method('delete');

        $service = $this->service(
            readiness: $readiness,
            runs: $runs,
            photoStore: $photoStore,
            catalogScope: $this->catalogScope(),
        );

        try {
            $service->start($this->startDto());
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $exception) {
            $this->assertSame('db down', $exception->getMessage());
        }
    }

    #[Test]
    public function start_marks_failed_when_queue_dispatch_throws(): void
    {
        $readiness = $this->createMock(SchedulePhotoReadinessCheckerInterface::class);
        $readiness->method('check')->willReturn($this->okReadiness());

        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('create')->willReturnCallback(
            fn (SchedulePhotoRunCreateDto $dto): SchedulePhotoRunRecord => $this->record(
                $dto->id,
                SchedulePhotoRunStatus::Pending,
                '/storage/photo.jpg',
            ),
        );
        $runs->expects($this->once())->method('update')->willReturnCallback(
            function (string $id, SchedulePhotoRunPatchDto $dto, array $expectedStatuses): SchedulePhotoRunRecord {
                $this->assertSame([SchedulePhotoRunStatus::Pending], $expectedStatuses);
                $this->assertTrue($dto->hasStatus());
                $this->assertSame(SchedulePhotoRunStatus::Failed, $dto->status);
                $this->assertTrue($dto->hasErrorMessage());
                $this->assertSame('queue down', $dto->errorMessage);

                return $this->record($id, SchedulePhotoRunStatus::Failed, '/storage/photo.jpg', null, 'queue down');
            },
        );

        $queue = $this->createMock(SchedulePhotoOcrQueueInterface::class);
        $queue->expects($this->once())->method('dispatch')->willThrowException(new \RuntimeException('queue down'));

        $photoStore = $this->createMock(SchedulePhotoPhotoStoreInterface::class);
        $photoStore->method('storeFromLocalPath')->willReturn('/storage/photo.jpg');
        $photoStore->expects($this->never())->method('delete');

        $service = $this->service(
            readiness: $readiness,
            runs: $runs,
            ocrQueue: $queue,
            photoStore: $photoStore,
            catalogScope: $this->catalogScope(),
        );

        try {
            $service->start($this->startDto());
            $this->fail('Expected SchedulePhotoDomainException');
        } catch (SchedulePhotoDomainException $exception) {
            $this->assertSame(503, $exception->statusCode());
            $this->assertSame('queue down', $exception->getMessage());
        }
    }

    #[Test]
    public function start_truncates_queue_error_and_fills_empty_message(): void
    {
        $readiness = $this->createMock(SchedulePhotoReadinessCheckerInterface::class);
        $readiness->method('check')->willReturn($this->okReadiness());

        $long = str_repeat('я', 2500);
        $seen = [];

        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('create')->willReturnCallback(
            fn (SchedulePhotoRunCreateDto $dto): SchedulePhotoRunRecord => $this->record(
                $dto->id,
                SchedulePhotoRunStatus::Pending,
                '/storage/photo.jpg',
            ),
        );
        $runs->method('update')->willReturnCallback(
            function (string $id, SchedulePhotoRunPatchDto $dto) use (&$seen): SchedulePhotoRunRecord {
                $seen[] = $dto->errorMessage;

                return $this->record(
                    $id,
                    SchedulePhotoRunStatus::Failed,
                    '/storage/photo.jpg',
                    null,
                    is_string($dto->errorMessage) ? $dto->errorMessage : null,
                );
            },
        );

        $queue = $this->createMock(SchedulePhotoOcrQueueInterface::class);
        $queue->method('dispatch')->willThrowException(new \RuntimeException($long));

        $service = $this->service(
            readiness: $readiness,
            runs: $runs,
            ocrQueue: $queue,
            photoStore: $this->storedPhoto(),
            catalogScope: $this->catalogScope(),
        );

        try {
            $service->start($this->startDto());
            $this->fail('Expected SchedulePhotoDomainException');
        } catch (SchedulePhotoDomainException $exception) {
            $this->assertSame(2000, mb_strlen($exception->getMessage()));
            $this->assertSame(mb_substr($long, 0, 2000), $exception->getMessage());
        }

        $queue = $this->createMock(SchedulePhotoOcrQueueInterface::class);
        $queue->method('dispatch')->willThrowException(new \RuntimeException('   '));
        $service = $this->service(
            readiness: $readiness,
            runs: $runs,
            ocrQueue: $queue,
            photoStore: $this->storedPhoto(),
            catalogScope: $this->catalogScope(),
        );

        try {
            $service->start($this->startDto());
            $this->fail('Expected SchedulePhotoDomainException');
        } catch (SchedulePhotoDomainException $exception) {
            $this->assertSame('Не удалось поставить прогон OCR в очередь.', $exception->getMessage());
        }

        $this->assertSame([
            mb_substr($long, 0, 2000),
            'Не удалось поставить прогон OCR в очередь.',
        ], $seen);
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
            function (string $id, SchedulePhotoRunPatchDto $dto, array $expectedStatuses): SchedulePhotoRunRecord {
                $this->assertSame([SchedulePhotoRunStatus::Pending], $expectedStatuses);
                $this->assertTrue($dto->hasStatus());
                $this->assertInstanceOf(SchedulePhotoRunStatus::class, $dto->status);

                return $this->record($id, $dto->status, '/tmp/photo.jpg');
            },
        );

        $orchestrator = $this->createMock(SchedulePhotoOcrOrchestratorInterface::class);
        $orchestrator->expects($this->once())->method('start')->with(
            $this->callback(function (SchedulePhotoOcrStartDto $dto) use ($runId): bool {
                $payload = $dto->toArray();

                return $dto->runId === $runId
                    && $dto->photoKey === 'schedule-photo/photo.jpg'
                    && $dto->photoMimeType === 'image/jpeg'
                    && $dto->aliasesMarkdown !== ''
                    && ($payload['photo_key'] ?? null) === 'schedule-photo/photo.jpg'
                    && ($payload['photo_mime_type'] ?? null) === 'image/jpeg'
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
        $photoStore->expects($this->once())
            ->method('mimeType')
            ->with('/tmp/photo.jpg')
            ->willReturn('image/jpeg');

        $aliases = $this->createMock(SchedulePhotoAliasesReaderInterface::class);
        $aliases->method('readMarkdown')->willReturn('| пюре | Картофельное пюре |');

        $catalog = $this->createMock(SchedulePhotoCatalogScopeBuilderInterface::class);
        $catalog->expects($this->once())->method('build')->with(
            'http://localhost:8083',
            1,
            [2],
        )->willReturn([
            new SchedulePhotoCatalogScopeDishDto(
                id: 1,
                name: 'Пюре',
                categoryId: 2,
                categoryName: 'Гарнир',
            ),
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
            function (string $id, SchedulePhotoRunPatchDto $dto, array $expectedStatuses): SchedulePhotoRunRecord {
                $this->assertSame([SchedulePhotoRunStatus::OcrRunning], $expectedStatuses);
                $this->assertTrue($dto->hasStatus());
                $this->assertSame(SchedulePhotoRunStatus::Matched, $dto->status);
                $this->assertTrue($dto->hasMatchResult());
                $this->assertInstanceOf(SchedulePhotoScheduleResultDto::class, $dto->matchResult);
                $this->assertSame(1, $dto->matchResult->matchedCount);

                return $this->record($id, SchedulePhotoRunStatus::Matched, '/tmp/photo.jpg', $dto->matchResult);
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
        )->willReturn(new SchedulePhotoScheduleResultDto(
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
            discarded: [new SchedulePhotoDiscardedRowDto(rawTitle: 'хз', reason: 'dish_not_found')],
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
            function (string $id, SchedulePhotoRunPatchDto $dto, array $expectedStatuses): SchedulePhotoRunRecord {
                $this->assertSame([SchedulePhotoRunStatus::OcrRunning], $expectedStatuses);
                $this->assertTrue($dto->hasStatus());
                $this->assertSame(SchedulePhotoRunStatus::Failed, $dto->status);
                $this->assertTrue($dto->hasErrorMessage());
                $this->assertSame('boom', $dto->errorMessage);

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
    public function complete_ocr_marks_failed_when_phototext_match_throws(): void
    {
        $runId = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';

        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('findById')->willReturn(
            $this->record($runId, SchedulePhotoRunStatus::OcrRunning, '/tmp/photo.jpg'),
        );
        $runs->expects($this->once())->method('update')->willReturnCallback(
            function (string $id, SchedulePhotoRunPatchDto $dto, array $expectedStatuses): SchedulePhotoRunRecord {
                $this->assertSame([SchedulePhotoRunStatus::OcrRunning], $expectedStatuses);
                $this->assertTrue($dto->hasStatus());
                $this->assertSame(SchedulePhotoRunStatus::Failed, $dto->status);
                $this->assertTrue($dto->hasErrorMessage());
                $this->assertSame('PhotoText match failed', $dto->errorMessage);
                $this->assertNotSame(SchedulePhotoRunStatus::OcrRunning, $dto->status);

                return $this->record(
                    $id,
                    SchedulePhotoRunStatus::Failed,
                    '/tmp/photo.jpg',
                    null,
                    'PhotoText match failed',
                );
            },
        );

        $photoText = $this->createMock(SchedulePhotoPhotoTextClientInterface::class);
        $photoText->expects($this->once())->method('match')
            ->willThrowException(new SchedulePhotoDomainException('PhotoText match failed', 502));

        $service = $this->service(runs: $runs, photoText: $photoText);

        $result = $service->completeOcr(new SchedulePhotoOcrCallbackDto(
            runId: $runId,
            entries: [['raw_title' => 'Пюре', 'dates' => ['2026-08-13']]],
        ));

        $this->assertSame(SchedulePhotoRunStatus::Failed, $result->status);
        $this->assertSame('PhotoText match failed', $result->errorMessage);
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

        $this->expectException(SchedulePhotoDomainException::class);
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
        $stored = $this->sampleMatchResult(
            matchedCount: 1,
            matched: [
                new SchedulePhotoScheduleMatchedDto(
                    rawTitle: 'пюре',
                    dishId: 10,
                    dishName: 'Картофельное пюре',
                    categoryId: 2,
                    categoryName: 'Гарнир',
                    dates: ['2026-08-13'],
                ),
            ],
            issues: [
                new SchedulePhotoScheduleIssueDto(
                    code: SchedulePhotoMatchIssueCode::DishNotFound,
                    message: 'не найдено',
                    rawTitle: 'хз',
                    dates: ['2026-08-14'],
                ),
            ],
        );

        $claimed = false;
        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('findById')->willReturn(
            $this->record($runId, SchedulePhotoRunStatus::Matched, '/tmp/photo.jpg', $stored),
        );
        $runs->expects($this->exactly(2))->method('update')->willReturnCallback(
            function (string $id, SchedulePhotoRunPatchDto $dto, array $expectedStatuses) use (&$claimed): SchedulePhotoRunRecord {
                if ($dto->status === SchedulePhotoRunStatus::Applying) {
                    $this->assertFalse($claimed);
                    $this->assertSame([SchedulePhotoRunStatus::Matched], $expectedStatuses);
                    $claimed = true;

                    return $this->record($id, SchedulePhotoRunStatus::Applying, '/tmp/photo.jpg');
                }

                $this->assertTrue($claimed);
                $this->assertSame(SchedulePhotoRunStatus::Applied, $dto->status);
                $this->assertSame([SchedulePhotoRunStatus::Applying], $expectedStatuses);
                $this->assertTrue($dto->hasMatchResult());
                $this->assertInstanceOf(SchedulePhotoScheduleResultDto::class, $dto->matchResult);
                $this->assertTrue($dto->matchResult->applied);
                $this->assertSame(1, $dto->matchResult->matchedCount);

                return $this->record($id, SchedulePhotoRunStatus::Applied, '/tmp/photo.jpg', $dto->matchResult);
            },
        );

        $photoText = $this->createMock(SchedulePhotoPhotoTextClientInterface::class);
        $photoText->expects($this->once())->method('apply')->with(
            'http://localhost:8083',
            1,
            [2],
            '2026-08-13',
            '2026-08-19',
            $this->callback(function (array $entries) use (&$claimed): bool {
                $this->assertTrue($claimed);
                $this->assertCount(2, $entries);
                $this->assertSame('Картофельное пюре', $entries[0]->name);
                $this->assertSame(['2026-08-13'], $entries[0]->dates);
                $this->assertSame('хз', $entries[1]->name);

                return true;
            }),
        )->willReturn(new SchedulePhotoScheduleResultDto(
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
        $stored = $this->sampleMatchResult(
            matchedCount: 0,
            matched: [],
            issues: [
                new SchedulePhotoScheduleIssueDto(
                    code: SchedulePhotoMatchIssueCode::DishNotFound,
                    message: 'не найдено',
                    rawTitle: 'хз',
                    dates: ['2026-08-14'],
                ),
            ],
        );

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
            $this->fail('Expected SchedulePhotoDomainException');
        } catch (SchedulePhotoDomainException $exception) {
            $this->assertSame(422, $exception->statusCode());
            $this->assertStringContainsString('matched_count = 0', $exception->getMessage());
        }
    }

    #[Test]
    public function apply_rolls_back_to_matched_when_phototext_throws(): void
    {
        $runId = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
        $stored = $this->samplePositiveMatchResult();

        $status = SchedulePhotoRunStatus::Matched;
        $errorMessage = null;
        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('findById')->willReturnCallback(
            function () use ($runId, $stored, &$status, &$errorMessage): SchedulePhotoRunRecord {
                return $this->record($runId, $status, '/tmp/photo.jpg', $stored, $errorMessage);
            },
        );
        $runs->expects($this->exactly(4))->method('update')->willReturnCallback(
            function (
                string $id,
                SchedulePhotoRunPatchDto $dto,
                array $expectedStatuses,
            ) use (&$status, &$errorMessage): SchedulePhotoRunRecord {
                if ($dto->status === SchedulePhotoRunStatus::Applying) {
                    $this->assertSame([SchedulePhotoRunStatus::Matched], $expectedStatuses);
                    $status = SchedulePhotoRunStatus::Applying;
                    $errorMessage = null;

                    return $this->record($id, $status, '/tmp/photo.jpg', null, $errorMessage);
                }

                if ($dto->status === SchedulePhotoRunStatus::Matched) {
                    $this->assertSame([SchedulePhotoRunStatus::Applying], $expectedStatuses);
                    $this->assertTrue($dto->hasErrorMessage());
                    $this->assertSame('PhotoText apply failed', $dto->errorMessage);
                    $status = SchedulePhotoRunStatus::Matched;
                    $errorMessage = $dto->errorMessage;

                    return $this->record($id, $status, '/tmp/photo.jpg', null, $errorMessage);
                }

                $this->assertSame(SchedulePhotoRunStatus::Applied, $dto->status);
                $this->assertSame([SchedulePhotoRunStatus::Applying], $expectedStatuses);
                $status = SchedulePhotoRunStatus::Applied;
                $errorMessage = null;

                return $this->record($id, $status, '/tmp/photo.jpg', $dto->matchResult, $errorMessage);
            },
        );

        $applyCalls = 0;
        $photoText = $this->createMock(SchedulePhotoPhotoTextClientInterface::class);
        $photoText->expects($this->exactly(2))->method('apply')
            ->willReturnCallback(function () use (&$applyCalls): SchedulePhotoScheduleResultDto {
                $applyCalls++;
                if ($applyCalls === 1) {
                    throw new \RuntimeException('PhotoText apply failed');
                }

                return new SchedulePhotoScheduleResultDto(
                    matchedCount: 1,
                    matched: [],
                    issues: [],
                    dateFrom: '2026-08-13',
                    dateTo: '2026-08-19',
                    applied: true,
                    categoriesApplied: [2],
                );
            });

        $service = $this->service(runs: $runs, photoText: $photoText);

        try {
            $service->apply($runId, 42);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $exception) {
            $this->assertSame('PhotoText apply failed', $exception->getMessage());
        }

        $this->assertSame(SchedulePhotoRunStatus::Matched, $status);
        $this->assertSame('PhotoText apply failed', $errorMessage);

        $result = $service->apply($runId, 42);
        $this->assertSame(SchedulePhotoRunStatus::Applied, $result->status);
    }

    #[Test]
    public function apply_rejects_when_status_is_not_matched(): void
    {
        $runId = '66666666-6666-4666-8666-666666666666';

        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('findById')->willReturn(
            $this->record($runId, SchedulePhotoRunStatus::OcrRunning, '/tmp/photo.jpg'),
        );
        $runs->expects($this->never())->method('update');

        $photoText = $this->createMock(SchedulePhotoPhotoTextClientInterface::class);
        $photoText->expects($this->never())->method('apply');

        $service = $this->service(runs: $runs, photoText: $photoText);

        $this->expectException(SchedulePhotoDomainException::class);
        $service->apply($runId, 42);
    }

    #[Test]
    public function apply_does_not_call_phototext_when_already_applied_or_applying(): void
    {
        foreach ([SchedulePhotoRunStatus::Applied, SchedulePhotoRunStatus::Applying] as $status) {
            $runId = '88888888-8888-4888-8888-888888888888';
            $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
            $runs->method('findById')->willReturn(
                $this->record($runId, $status, '/tmp/photo.jpg', $this->sampleMatchResult(
                    matchedCount: 1,
                    matched: [
                        new SchedulePhotoScheduleMatchedDto(
                            rawTitle: '',
                            dishId: 0,
                            dishName: 'Картофельное пюре',
                            categoryId: 0,
                            categoryName: '',
                            dates: ['2026-08-13'],
                        ),
                    ],
                )),
            );
            $runs->expects($this->never())->method('update');

            $photoText = $this->createMock(SchedulePhotoPhotoTextClientInterface::class);
            $photoText->expects($this->never())->method('apply');

            $service = $this->service(runs: $runs, photoText: $photoText);

            try {
                $service->apply($runId, 42);
                $this->fail('Expected SchedulePhotoDomainException for '.$status->value);
            } catch (SchedulePhotoDomainException $exception) {
                $this->assertSame(409, $exception->statusCode());
            }
        }
    }

    #[Test]
    public function apply_does_not_call_phototext_when_matched_claim_lost(): void
    {
        $runId = '99999999-9999-4999-8999-999999999999';
        $stored = $this->samplePositiveMatchResult();

        $reads = 0;
        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('findById')->willReturnCallback(
            function () use (&$reads, $runId, $stored): SchedulePhotoRunRecord {
                $reads++;
                $status = $reads === 1
                    ? SchedulePhotoRunStatus::Matched
                    : SchedulePhotoRunStatus::Applying;

                return $this->record($runId, $status, '/tmp/photo.jpg', $stored);
            },
        );
        $runs->expects($this->once())->method('update')->willReturnCallback(
            function (string $id, SchedulePhotoRunPatchDto $dto, array $expectedStatuses): ?SchedulePhotoRunRecord {
                $this->assertTrue($dto->hasStatus());
                $this->assertSame(SchedulePhotoRunStatus::Applying, $dto->status);
                $this->assertSame([SchedulePhotoRunStatus::Matched], $expectedStatuses);

                return null;
            },
        );

        $photoText = $this->createMock(SchedulePhotoPhotoTextClientInterface::class);
        $photoText->expects($this->never())->method('apply');

        $service = $this->service(runs: $runs, photoText: $photoText);

        try {
            $service->apply($runId, 42);
            $this->fail('Expected SchedulePhotoDomainException');
        } catch (SchedulePhotoDomainException $exception) {
            $this->assertSame(409, $exception->statusCode());
            $this->assertSame('Запись графика уже выполняется.', $exception->getMessage());
        }
    }

    #[Test]
    public function fail_queued_ocr_updates_only_pending_or_ocr_running(): void
    {
        $runId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $runs->method('findById')->willReturn(
            $this->record($runId, SchedulePhotoRunStatus::OcrRunning, '/tmp/photo.jpg'),
        );
        $runs->expects($this->once())->method('update')->willReturnCallback(
            function (string $id, SchedulePhotoRunPatchDto $dto, array $expectedStatuses): SchedulePhotoRunRecord {
                $this->assertTrue($dto->hasStatus());
                $this->assertSame(SchedulePhotoRunStatus::Failed, $dto->status);
                $this->assertSame(
                    [SchedulePhotoRunStatus::Pending, SchedulePhotoRunStatus::OcrRunning],
                    $expectedStatuses,
                );

                return $this->record($id, SchedulePhotoRunStatus::Failed, '/tmp/photo.jpg');
            },
        );

        $this->service(runs: $runs)->failQueuedOcr($runId, 'timeout');
    }

    #[Test]
    public function fail_queued_ocr_does_not_touch_matched_or_applied(): void
    {
        foreach ([SchedulePhotoRunStatus::Matched, SchedulePhotoRunStatus::Applied, SchedulePhotoRunStatus::Applying] as $status) {
            $runId = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
            $runs = $this->createMock(SchedulePhotoRunRepositoryInterface::class);
            $runs->method('findById')->willReturn(
                $this->record($runId, $status, '/tmp/photo.jpg'),
            );
            $runs->expects($this->never())->method('update');

            $this->service(runs: $runs)->failQueuedOcr($runId, 'timeout');
        }
    }

    #[Test]
    public function apply_hides_foreign_run_as_not_found_without_writing(): void
    {
        $runId = '77777777-7777-4777-8777-777777777777';
        $stored = $this->samplePositiveMatchResult();

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
            $this->fail('Expected SchedulePhotoDomainException');
        } catch (SchedulePhotoDomainException $exception) {
            $this->assertSame(404, $exception->statusCode());
            $this->assertSame('Прогон SchedulePhoto не найден.', $exception->getMessage());
        }
    }

    /**
     * Композит четырёх сценариев (starter / applier / OCR completer / job handler)
     * с теми же зависимостями, что раньше у SchedulePhotoRunService.
     */
    private function service(
        ?SchedulePhotoReadinessCheckerInterface $readiness = null,
        ?SchedulePhotoRunRepositoryInterface $runs = null,
        ?SchedulePhotoOcrQueueInterface $ocrQueue = null,
        ?SchedulePhotoOcrOrchestratorInterface $ocrOrchestrator = null,
        ?SchedulePhotoPhotoStoreInterface $photoStore = null,
        ?SchedulePhotoIdGeneratorInterface $idGenerator = null,
        ?SchedulePhotoAliasesReaderInterface $aliasesReader = null,
        ?SchedulePhotoCatalogScopeBuilderInterface $catalogScope = null,
        ?SchedulePhotoPhotoTextClientInterface $photoText = null,
    ): object {
        $runs ??= $this->createMock(SchedulePhotoRunRepositoryInterface::class);
        $access = new SchedulePhotoRunAccess($runs);
        $photoStore ??= $this->createMock(SchedulePhotoPhotoStoreInterface::class);
        $catalogScope ??= $this->createMock(SchedulePhotoCatalogScopeBuilderInterface::class);
        $photoText ??= $this->createMock(SchedulePhotoPhotoTextClientInterface::class);

        $starter = new SchedulePhotoRunStarter(
            readiness: $readiness ?? $this->createMock(SchedulePhotoReadinessCheckerInterface::class),
            runs: $runs,
            access: $access,
            ocrQueue: $ocrQueue ?? $this->createMock(SchedulePhotoOcrQueueInterface::class),
            photoStore: $photoStore,
            idGenerator: $idGenerator ?? $this->fixedIds(),
            catalogScope: $catalogScope,
        );
        $applier = new SchedulePhotoRunApplier(
            runs: $runs,
            access: $access,
            photoText: $photoText,
            storedMatchEntries: new SchedulePhotoStoredMatchEntries(),
        );
        $completer = new SchedulePhotoOcrCompleter(
            runs: $runs,
            access: $access,
            photoText: $photoText,
        );
        $jobs = new SchedulePhotoOcrJobHandler(
            runs: $runs,
            ocrOrchestrator: $ocrOrchestrator ?? $this->createMock(SchedulePhotoOcrOrchestratorInterface::class),
            photoStore: $photoStore,
            aliasesReader: $aliasesReader ?? $this->createMock(SchedulePhotoAliasesReaderInterface::class),
            catalogScope: $catalogScope,
        );

        return new class ($starter, $applier, $completer, $jobs) {
            public function __construct(
                private readonly SchedulePhotoRunStarter $starter,
                private readonly SchedulePhotoRunApplier $applier,
                private readonly SchedulePhotoOcrCompleter $completer,
                private readonly SchedulePhotoOcrJobHandler $jobs,
            ) {}

            public function start(SchedulePhotoStartRunDto $dto): SchedulePhotoRunRecord
            {
                return $this->starter->start($dto);
            }

            public function getForAdmin(string $runId, int $actorMaxUserId): SchedulePhotoRunRecord
            {
                return $this->starter->getForAdmin($runId, $actorMaxUserId);
            }

            public function apply(string $runId, int $actorMaxUserId): SchedulePhotoRunRecord
            {
                return $this->applier->apply($runId, $actorMaxUserId);
            }

            public function completeOcr(SchedulePhotoOcrCallbackDto $dto): SchedulePhotoRunRecord
            {
                return $this->completer->completeOcr($dto);
            }

            public function performQueuedOcr(string $runId): void
            {
                $this->jobs->performQueuedOcr($runId);
            }

            public function failQueuedOcr(string $runId, ?string $reason = null): void
            {
                $this->jobs->failQueuedOcr($runId, $reason);
            }
        };
    }

    private function fixedIds(string $id = '11111111-1111-4111-8111-111111111111'): SchedulePhotoIdGeneratorInterface
    {
        $ids = $this->createMock(SchedulePhotoIdGeneratorInterface::class);
        $ids->method('generate')->willReturn($id);

        return $ids;
    }

    private function startDto(): SchedulePhotoStartRunDto
    {
        return new SchedulePhotoStartRunDto(
            baseUrl: 'http://localhost:8083',
            dateFrom: '2026-08-13',
            restaurantId: 1,
            categoryIds: [2],
            photoAbsolutePath: '/tmp/x',
            createdByMaxUserId: 42,
        );
    }

    private function catalogScope(): SchedulePhotoCatalogScopeBuilderInterface
    {
        $catalog = $this->createMock(SchedulePhotoCatalogScopeBuilderInterface::class);
        $catalog->method('build')->willReturn([
            new SchedulePhotoCatalogScopeDishDto(
                id: 1,
                name: 'Пюре',
                categoryId: 2,
                categoryName: 'Гарнир',
            ),
        ]);

        return $catalog;
    }

    private function storedPhoto(): SchedulePhotoPhotoStoreInterface
    {
        $photoStore = $this->createMock(SchedulePhotoPhotoStoreInterface::class);
        $photoStore->method('storeFromLocalPath')->willReturn('/storage/photo.jpg');
        $photoStore->expects($this->never())->method('delete');

        return $photoStore;
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
     * @param  list<SchedulePhotoDiscardedRowDto>  $discarded
     */
    private function record(
        string $id,
        SchedulePhotoRunStatus $status,
        ?string $photoPath,
        ?SchedulePhotoScheduleResultDto $matchResult = null,
        ?string $errorMessage = null,
        array $discarded = [],
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
            discarded: $discarded,
            errorMessage: $errorMessage,
        );
    }

    /**
     * @param  list<SchedulePhotoScheduleMatchedDto>  $matched
     * @param  list<SchedulePhotoScheduleIssueDto>  $issues
     * @param  list<int>  $categoriesApplied
     */
    private function sampleMatchResult(
        int $matchedCount,
        array $matched = [],
        array $issues = [],
        bool $applied = false,
        array $categoriesApplied = [],
    ): SchedulePhotoScheduleResultDto {
        return new SchedulePhotoScheduleResultDto(
            matchedCount: $matchedCount,
            matched: $matched,
            issues: $issues,
            dateFrom: '2026-08-13',
            dateTo: '2026-08-19',
            applied: $applied,
            categoriesApplied: $categoriesApplied,
        );
    }

    private function samplePositiveMatchResult(): SchedulePhotoScheduleResultDto
    {
        return $this->sampleMatchResult(
            matchedCount: 1,
            matched: [
                new SchedulePhotoScheduleMatchedDto(
                    rawTitle: 'пюре',
                    dishId: 10,
                    dishName: 'Картофельное пюре',
                    categoryId: 2,
                    categoryName: 'Гарнир',
                    dates: ['2026-08-13'],
                ),
            ],
        );
    }
}
