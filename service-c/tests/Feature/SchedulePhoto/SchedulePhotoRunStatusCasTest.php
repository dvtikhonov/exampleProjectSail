<?php

declare(strict_types=1);

namespace Tests\Feature\SchedulePhoto;

use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoOcrJobInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRunPatchDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleResultDto;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Domain\Repository\SchedulePhotoRunRepositoryInterface;
use App\Modules\SchedulePhoto\Infrastructure\Persistence\Eloquent\SchedulePhotoRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Сравнение-и-запись статуса прогона: UPDATE … WHERE id AND status.
 */
class SchedulePhotoRunStatusCasTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_changes_row_only_from_expected_status(): void
    {
        $runId = $this->createRun(SchedulePhotoRunStatus::Matched);
        $runs = $this->app->make(SchedulePhotoRunRepositoryInterface::class);

        $lost = $runs->update(
            $runId,
            SchedulePhotoRunPatchDto::change(
                status: SchedulePhotoRunStatus::Failed,
                errorMessage: 'job failed',
            ),
            [
                SchedulePhotoRunStatus::Pending,
                SchedulePhotoRunStatus::OcrRunning,
            ],
        );

        $this->assertNull($lost);
        $this->assertSame(SchedulePhotoRunStatus::Matched, $runs->findById($runId)?->status);

        $claimed = $runs->update(
            $runId,
            SchedulePhotoRunPatchDto::change(
                status: SchedulePhotoRunStatus::Applying,
                errorMessage: null,
            ),
            [SchedulePhotoRunStatus::Matched],
        );

        $this->assertNotNull($claimed);
        $this->assertSame(SchedulePhotoRunStatus::Applying, $claimed->status);

        $secondClaim = $runs->update(
            $runId,
            SchedulePhotoRunPatchDto::change(
                status: SchedulePhotoRunStatus::Applying,
                errorMessage: null,
            ),
            [SchedulePhotoRunStatus::Matched],
        );

        $this->assertNull($secondClaim);
        $this->assertSame(SchedulePhotoRunStatus::Applying, $runs->findById($runId)?->status);

        $applied = $runs->update(
            $runId,
            SchedulePhotoRunPatchDto::change(
                status: SchedulePhotoRunStatus::Applied,
                matchResult: new SchedulePhotoScheduleResultDto(
                    matchedCount: 1,
                    matched: [],
                    issues: [],
                    dateFrom: '2026-08-13',
                    dateTo: '2026-08-19',
                    applied: true,
                    categoriesApplied: [2],
                ),
                errorMessage: null,
            ),
            [SchedulePhotoRunStatus::Applying],
        );

        $this->assertNotNull($applied);
        $this->assertSame(SchedulePhotoRunStatus::Applied, $applied->status);
        $this->assertTrue($applied->matchResult?->applied ?? false);
        $this->assertSame(1, $applied->matchResult?->matchedCount);
    }

    public function test_fail_queued_ocr_does_not_overwrite_matched(): void
    {
        $runId = $this->createRun(SchedulePhotoRunStatus::Matched);
        $service = $this->app->make(SchedulePhotoOcrJobInterface::class);

        $service->failQueuedOcr($runId, 'OCR job failed or timed out.');

        $run = SchedulePhotoRun::query()->findOrFail($runId);
        $this->assertSame(SchedulePhotoRunStatus::Matched, $run->status);
        $this->assertNull($run->error_message);
    }

    public function test_fail_queued_ocr_marks_pending_and_ocr_running(): void
    {
        $service = $this->app->make(SchedulePhotoOcrJobInterface::class);

        $pendingId = $this->createRun(SchedulePhotoRunStatus::Pending);
        $service->failQueuedOcr($pendingId, 'timeout');
        $pending = SchedulePhotoRun::query()->findOrFail($pendingId);
        $this->assertSame(SchedulePhotoRunStatus::Failed, $pending->status);
        $this->assertSame('timeout', $pending->error_message);

        $runningId = $this->createRun(SchedulePhotoRunStatus::OcrRunning);
        $service->failQueuedOcr($runningId, 'timeout');
        $running = SchedulePhotoRun::query()->findOrFail($runningId);
        $this->assertSame(SchedulePhotoRunStatus::Failed, $running->status);
        $this->assertSame('timeout', $running->error_message);

        $appliedId = $this->createRun(SchedulePhotoRunStatus::Applied);
        $service->failQueuedOcr($appliedId, 'timeout');
        $applied = SchedulePhotoRun::query()->findOrFail($appliedId);
        $this->assertSame(SchedulePhotoRunStatus::Applied, $applied->status);
        $this->assertNull($applied->error_message);
    }

    private function createRun(SchedulePhotoRunStatus $status): string
    {
        $runId = (string) Str::uuid();
        SchedulePhotoRun::query()->create([
            'id' => $runId,
            'status' => $status,
            'base_url' => 'http://localhost:8083',
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'restaurant_id' => 1,
            'category_ids' => [2],
            'created_by_max_user_id' => 71_099,
            'photo_path' => '/tmp/photo.jpg',
            'match_result' => null,
            'discarded' => [],
        ]);

        return $runId;
    }
}
