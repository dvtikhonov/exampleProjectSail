<?php

declare(strict_types=1);

namespace Tests\Feature\SchedulePhoto;

use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Max\MaxUser;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoCatalogScopeBuilderInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoReadinessCheckerInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoCatalogScopeDishDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoReadinessResultDto;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoOcrQueueInterface;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Infrastructure\Persistence\Eloquent\SchedulePhotoRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Feature: POST /api/food/admin/schedule-photo/runs (старт OCR).
 */
class AdminSchedulePhotoStoreApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string ENDPOINT = '/api/food/admin/schedule-photo/runs';

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();

        config([
            'schedule_photo.allowed_base_urls' => [
                'http://localhost:8083',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_requires_authentication(): void
    {
        $this->postRun($this->validFields())
            ->assertUnauthorized();
    }

    public function test_forbidden_without_menu_manager_role(): void
    {
        $auth = $this->authenticateMaxUser(MaxUser::query()->create([
            'max_user_id' => 81_001,
            'first_name' => 'NoRole',
        ]));

        $this->postRun($this->validFields(), $auth['headers'])
            ->assertForbidden();
    }

    public function test_validation_requires_photo(): void
    {
        $menu = $this->menuManagerAuth(81_002);

        $this->postRun($this->validFields(), $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);

        $this->assertDatabaseCount('schedule_photo_runs', 0);
    }

    public function test_rejects_unsupported_photo_mime(): void
    {
        $menu = $this->menuManagerAuth(81_003);

        $this->postRun($this->validFields([
            'photo' => UploadedFile::fake()->create('schedule.bmp', 10, 'image/bmp'),
        ]), $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);

        $this->assertDatabaseCount('schedule_photo_runs', 0);
    }

    public function test_unprocessable_when_readiness_fails(): void
    {
        $menu = $this->menuManagerAuth(81_004);

        $this->mock(SchedulePhotoReadinessCheckerInterface::class, function ($mock): void {
            $mock->shouldReceive('check')->once()->andReturn(new SchedulePhotoReadinessResultDto(
                ok: false,
                aiAccessOk: false,
                aiAccessMessage: 'AI off',
                photoTextOk: true,
                photoTextMessage: 'ok',
                sidecarOk: true,
                sidecarMessage: 'ok',
            ));
        });
        $this->mock(SchedulePhotoOcrQueueInterface::class, function ($mock): void {
            $mock->shouldReceive('dispatch')->never();
        });

        $this->postRun($this->validFields([
            'photo' => $this->jpegUpload(),
        ]), $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'AI off');

        $this->assertDatabaseCount('schedule_photo_runs', 0);
    }

    public function test_accepted_creates_pending_run_and_dispatches_queue(): void
    {
        $menu = $this->menuManagerAuth(81_010);
        $dispatchedRunId = null;

        $this->mock(SchedulePhotoReadinessCheckerInterface::class, function ($mock): void {
            $mock->shouldReceive('check')
                ->once()
                ->with('http://localhost:8083')
                ->andReturn(new SchedulePhotoReadinessResultDto(
                    ok: true,
                    aiAccessOk: true,
                    aiAccessMessage: 'ok',
                    photoTextOk: true,
                    photoTextMessage: 'ok',
                    sidecarOk: true,
                    sidecarMessage: 'ok',
                ));
        });
        $this->mock(SchedulePhotoCatalogScopeBuilderInterface::class, function ($mock): void {
            $mock->shouldReceive('build')->andReturn([
                new SchedulePhotoCatalogScopeDishDto(
                    id: 1,
                    name: 'Пюре',
                    categoryId: 2,
                    categoryName: 'Гарнир',
                ),
            ]);
        });
        $this->mock(SchedulePhotoOcrQueueInterface::class, function ($mock) use (&$dispatchedRunId): void {
            $mock->shouldReceive('dispatch')
                ->once()
                ->withArgs(function (string $runId) use (&$dispatchedRunId): bool {
                    $dispatchedRunId = $runId;

                    return $runId !== '';
                });
        });

        $response = $this->postRun($this->validFields([
            'photo' => $this->jpegUpload(),
        ]), $menu['headers']);

        $response->assertAccepted()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('base_url', 'http://localhost:8083')
            ->assertJsonPath('date_from', '2026-08-13')
            ->assertJsonPath('date_to', '2026-08-19')
            ->assertJsonPath('restaurant_id', 1)
            ->assertJsonPath('category_ids', [2])
            ->assertJsonPath('created_by_max_user_id', 81_010);

        $runId = $response->json('id');
        $this->assertIsString($runId);
        $this->assertSame($runId, $dispatchedRunId);

        $this->assertDatabaseHas('schedule_photo_runs', [
            'id' => $runId,
            'status' => 'pending',
            'base_url' => 'http://localhost:8083',
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'restaurant_id' => 1,
            'created_by_max_user_id' => 81_010,
        ]);

        $photoPath = SchedulePhotoRun::query()->find($runId)?->photo_path;
        if (is_string($photoPath) && is_file($photoPath)) {
            unlink($photoPath);
        }
    }

    public function test_queue_dispatch_failure_marks_run_failed_and_keeps_photo(): void
    {
        $menu = $this->menuManagerAuth(81_011);

        $this->mock(SchedulePhotoReadinessCheckerInterface::class, function ($mock): void {
            $mock->shouldReceive('check')->once()->andReturn(new SchedulePhotoReadinessResultDto(
                ok: true,
                aiAccessOk: true,
                aiAccessMessage: 'ok',
                photoTextOk: true,
                photoTextMessage: 'ok',
                sidecarOk: true,
                sidecarMessage: 'ok',
            ));
        });
        $this->mock(SchedulePhotoCatalogScopeBuilderInterface::class, function ($mock): void {
            $mock->shouldReceive('build')->andReturn([
                new SchedulePhotoCatalogScopeDishDto(
                    id: 1,
                    name: 'Пюре',
                    categoryId: 2,
                    categoryName: 'Гарнир',
                ),
            ]);
        });
        $this->mock(SchedulePhotoOcrQueueInterface::class, function ($mock): void {
            $mock->shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('queue down'));
        });

        $photoPath = null;

        try {
            $this->postRun($this->validFields([
                'photo' => $this->jpegUpload(),
            ]), $menu['headers'])
                ->assertStatus(503)
                ->assertJsonPath('message', 'queue down');

            $this->assertDatabaseCount('schedule_photo_runs', 1);
            $run = SchedulePhotoRun::query()->first();
            $this->assertNotNull($run);
            $this->assertSame(SchedulePhotoRunStatus::Failed, $run->status);
            $this->assertSame('queue down', $run->error_message);
            $this->assertDatabaseMissing('schedule_photo_runs', [
                'status' => 'pending',
            ]);

            $photoPath = $run->photo_path;
            $this->assertIsString($photoPath);
            $this->assertFileExists($photoPath);
        } finally {
            if (is_string($photoPath) && is_file($photoPath)) {
                unlink($photoPath);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validFields(array $overrides = []): array
    {
        return array_merge([
            'base_url' => 'http://localhost:8083',
            'date_from' => '2026-08-13',
            'restaurant_id' => 1,
            'category_ids' => [2],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function postRun(array $payload, array $headers = []): TestResponse
    {
        return $this->withHeaders(array_merge([
            'Accept' => 'application/json',
        ], $headers))->post(self::ENDPOINT, $payload);
    }

    /**
     * @return array{user: MaxUser, headers: array<string, string>}
     */
    private function menuManagerAuth(int $maxUserId): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => $maxUserId,
                'first_name' => 'MenuManager'.$maxUserId,
            ])),
            FoodOrderAdminRole::MenuManager,
        );
    }

    /**
     * JPEG 1×1: finfo → image/jpeg, dimensions проходят правило photo.
     */
    private function jpegUpload(): UploadedFile
    {
        $bytes = base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=',
            true,
        );
        $this->assertIsString($bytes);

        $path = tempnam(sys_get_temp_dir(), 'schedule_photo_store_');
        $this->assertNotFalse($path);
        file_put_contents($path, $bytes);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, 'schedule.jpg', 'image/jpeg', null, true);
    }
}
