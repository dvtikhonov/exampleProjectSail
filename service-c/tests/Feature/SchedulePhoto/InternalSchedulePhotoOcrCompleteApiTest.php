<?php

declare(strict_types=1);

namespace Tests\Feature\SchedulePhoto;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleResultDto;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Infrastructure\Persistence\Eloquent\SchedulePhotoRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature: POST /api/food/internal/schedule-photo/ocr-complete.
 */
class InternalSchedulePhotoOcrCompleteApiTest extends TestCase
{
    use RefreshDatabase;

    private const string ENDPOINT = '/api/food/internal/schedule-photo/ocr-complete';

    private const string SECRET = 'schedule-photo-test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'schedule_photo.capture_secret' => self::SECRET,
        ]);
    }

    public function test_rejects_without_secret(): void
    {
        $this->postJson(self::ENDPOINT, [
            'run_id' => (string) Str::uuid(),
            'error' => 'x',
        ])->assertUnauthorized();
    }

    public function test_success_callback_runs_matcher(): void
    {
        $runId = (string) Str::uuid();
        SchedulePhotoRun::query()->create([
            'id' => $runId,
            'status' => SchedulePhotoRunStatus::OcrRunning,
            'base_url' => 'http://localhost:8083',
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'restaurant_id' => 1,
            'category_ids' => [2],
            'created_by_max_user_id' => 1,
            'photo_path' => '/tmp/photo.jpg',
            'discarded' => [],
        ]);

        $this->mock(SchedulePhotoPhotoTextClientInterface::class, function ($mock): void {
            $mock->shouldReceive('match')->once()->andReturn(new SchedulePhotoScheduleResultDto(
                matchedCount: 0,
                matched: [],
                issues: [],
                dateFrom: '2026-08-13',
                dateTo: '2026-08-19',
            ));
        });

        $this->postJson(self::ENDPOINT, [
            'run_id' => $runId,
            'entries' => [
                ['raw_title' => 'Пюре', 'dates' => ['2026-08-13']],
            ],
            'discarded' => [
                [
                    'raw_title' => 'хз',
                    'reason' => 'dish_not_found',
                    'dates' => ['2026-08-14'],
                ],
            ],
        ], [
            'X-Schedule-Photo-Capture-Secret' => self::SECRET,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('run.status', 'matched');

        $this->assertDatabaseHas('schedule_photo_runs', [
            'id' => $runId,
            'status' => 'matched',
        ]);

        $stored = SchedulePhotoRun::query()->findOrFail($runId);
        $this->assertSame('хз', $stored->discarded[0]['raw_title'] ?? null);
        $this->assertSame(['2026-08-14'], $stored->discarded[0]['dates'] ?? null);
    }

    public function test_error_callback_marks_failed(): void
    {
        $runId = (string) Str::uuid();
        SchedulePhotoRun::query()->create([
            'id' => $runId,
            'status' => SchedulePhotoRunStatus::OcrRunning,
            'base_url' => 'http://localhost:8083',
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'restaurant_id' => 1,
            'category_ids' => [2],
            'created_by_max_user_id' => 1,
            'photo_path' => '/tmp/photo.jpg',
            'discarded' => [],
        ]);

        $this->postJson(self::ENDPOINT, [
            'run_id' => $runId,
            'error' => 'llm timeout',
        ], [
            'X-Schedule-Photo-Capture-Secret' => self::SECRET,
        ])
            ->assertOk()
            ->assertJsonPath('run.status', 'failed')
            ->assertJsonPath('run.error_message', 'llm timeout');
    }

    public function test_rejects_duplicate_discarded_raw_titles(): void
    {
        $runId = (string) Str::uuid();
        SchedulePhotoRun::query()->create([
            'id' => $runId,
            'status' => SchedulePhotoRunStatus::OcrRunning,
            'base_url' => 'http://localhost:8083',
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'restaurant_id' => 1,
            'category_ids' => [2],
            'created_by_max_user_id' => 1,
            'photo_path' => '/tmp/photo.jpg',
            'discarded' => [],
        ]);

        $this->postJson(self::ENDPOINT, [
            'run_id' => $runId,
            'entries' => [],
            'discarded' => [
                ['raw_title' => 'пюрэшка', 'reason' => 'dish_not_found', 'dates' => ['2026-08-13']],
                ['raw_title' => 'пюрэшка', 'reason' => 'low_confidence', 'dates' => ['2026-08-14']],
            ],
        ], [
            'X-Schedule-Photo-Capture-Secret' => self::SECRET,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discarded.1.raw_title']);

        $this->assertDatabaseHas('schedule_photo_runs', [
            'id' => $runId,
            'status' => 'ocr_running',
        ]);
    }

    public function test_allows_empty_entries_array(): void
    {
        $runId = (string) Str::uuid();
        SchedulePhotoRun::query()->create([
            'id' => $runId,
            'status' => SchedulePhotoRunStatus::OcrRunning,
            'base_url' => 'http://localhost:8083',
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'restaurant_id' => 1,
            'category_ids' => [2],
            'created_by_max_user_id' => 1,
            'photo_path' => '/tmp/photo.jpg',
            'discarded' => [],
        ]);

        $this->mock(SchedulePhotoPhotoTextClientInterface::class, function ($mock): void {
            $mock->shouldReceive('match')->once()->andReturn(new SchedulePhotoScheduleResultDto(
                matchedCount: 0,
                matched: [],
                issues: [],
                dateFrom: '2026-08-13',
                dateTo: '2026-08-19',
            ));
        });

        $this->postJson(self::ENDPOINT, [
            'run_id' => $runId,
            'entries' => [],
        ], [
            'X-Schedule-Photo-Capture-Secret' => self::SECRET,
        ])->assertOk()->assertJsonPath('run.status', 'matched');
    }
}
