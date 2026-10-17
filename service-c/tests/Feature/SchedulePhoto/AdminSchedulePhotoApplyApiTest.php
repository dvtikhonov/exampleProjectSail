<?php

declare(strict_types=1);

namespace Tests\Feature\SchedulePhoto;

use App\DTO\Food\PhotoText\PhotoTextScheduleEntryDto;
use App\DTO\Food\PhotoText\PhotoTextScheduleResultDto;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Max\MaxUser;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoCatalogScopeBuilderInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\Enums\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Models\SchedulePhotoRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Feature: POST /api/food/admin/schedule-photo/runs/{id}/apply.
 */
class AdminSchedulePhotoApplyApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    public function test_requires_authentication(): void
    {
        $runId = (string) Str::uuid();

        $this->postJson($this->endpoint($runId))
            ->assertUnauthorized();
    }

    public function test_forbidden_without_menu_manager_role(): void
    {
        $auth = $this->authenticateMaxUser(MaxUser::query()->create([
            'max_user_id' => 71_001,
            'first_name' => 'NoRole',
        ]));
        $runId = $this->createMatchedRun(matchedCount: 1, createdByMaxUserId: 71_001);

        $this->postJson($this->endpoint($runId), [], $auth['headers'])
            ->assertForbidden();
    }

    public function test_apply_writes_schedule_when_matched_and_confirmed(): void
    {
        $menu = $this->menuManagerAuth(71_010);
        $runId = $this->createMatchedRun(matchedCount: 1, createdByMaxUserId: 71_010);

        $this->mock(SchedulePhotoCatalogScopeBuilderInterface::class, function ($mock): void {
            $mock->shouldReceive('build')->andReturn([
                [
                    'id' => 10,
                    'name' => 'Картофельное пюре',
                    'category_id' => 2,
                    'category_name' => 'Гарнир',
                ],
            ]);
        });

        $this->mock(SchedulePhotoPhotoTextClientInterface::class, function ($mock): void {
            $mock->shouldReceive('apply')
                ->once()
                ->withArgs(function (
                    string $baseUrl,
                    int $restaurantId,
                    ?array $categoryIds,
                    string $dateFrom,
                    string $dateTo,
                    array $entries,
                ): bool {
                    $this->assertSame('http://localhost:8083', $baseUrl);
                    $this->assertSame(1, $restaurantId);
                    $this->assertSame([2], $categoryIds);
                    $this->assertSame('2026-08-13', $dateFrom);
                    $this->assertSame('2026-08-19', $dateTo);
                    $this->assertNotEmpty($entries);
                    $this->assertContainsOnlyInstancesOf(PhotoTextScheduleEntryDto::class, $entries);

                    return true;
                })
                ->andReturn(new PhotoTextScheduleResultDto(
                    matchedCount: 1,
                    matched: [],
                    issues: [],
                    dateFrom: '2026-08-13',
                    dateTo: '2026-08-19',
                    applied: true,
                    categoriesApplied: [2],
                ));
        });

        $this->postJson($this->endpoint($runId), [], $menu['headers'])
            ->assertOk()
            ->assertJsonPath('status', 'applied')
            ->assertJsonPath('match_result.applied', true)
            ->assertJsonPath('match_result.matched_count', 1);

        $this->assertDatabaseHas('schedule_photo_runs', [
            'id' => $runId,
            'status' => 'applied',
        ]);
    }

    public function test_apply_stops_when_matched_count_is_zero(): void
    {
        $menu = $this->menuManagerAuth(71_011);
        $runId = $this->createMatchedRun(matchedCount: 0, createdByMaxUserId: 71_011);

        $this->mock(SchedulePhotoPhotoTextClientInterface::class, function ($mock): void {
            $mock->shouldReceive('apply')->never();
        });

        $this->postJson($this->endpoint($runId), [], $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn ($message): bool => is_string($message)
                && str_contains($message, 'matched_count = 0'));

        $this->assertDatabaseHas('schedule_photo_runs', [
            'id' => $runId,
            'status' => 'matched',
        ]);
    }

    public function test_apply_rejects_when_run_not_matched(): void
    {
        $menu = $this->menuManagerAuth(71_012);
        $runId = (string) Str::uuid();
        SchedulePhotoRun::query()->create([
            'id' => $runId,
            'status' => SchedulePhotoRunStatus::Pending,
            'base_url' => 'http://localhost:8083',
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'restaurant_id' => 1,
            'category_ids' => [2],
            'created_by_max_user_id' => 71_012,
            'photo_path' => '/tmp/photo.jpg',
            'match_result' => null,
            'discarded' => [],
        ]);

        $this->mock(SchedulePhotoPhotoTextClientInterface::class, function ($mock): void {
            $mock->shouldReceive('apply')->never();
        });

        $this->postJson($this->endpoint($runId), [], $menu['headers'])
            ->assertStatus(409);
    }

    private function endpoint(string $runId): string
    {
        return '/api/food/admin/schedule-photo/runs/'.$runId.'/apply';
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

    private function createMatchedRun(int $matchedCount, int $createdByMaxUserId): string
    {
        $runId = (string) Str::uuid();
        $matched = $matchedCount > 0
            ? [[
                'raw_title' => 'пюре',
                'dish_id' => 10,
                'dish_name' => 'Картофельное пюре',
                'category_id' => 2,
                'category_name' => 'Гарнир',
                'dates' => ['2026-08-13'],
            ]]
            : [];

        SchedulePhotoRun::query()->create([
            'id' => $runId,
            'status' => SchedulePhotoRunStatus::Matched,
            'base_url' => 'http://localhost:8083',
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'restaurant_id' => 1,
            'category_ids' => [2],
            'created_by_max_user_id' => $createdByMaxUserId,
            'photo_path' => '/tmp/photo.jpg',
            'match_result' => [
                'matched_count' => $matchedCount,
                'matched' => $matched,
                'issues' => $matchedCount === 0
                    ? [[
                        'code' => 'dish_not_found',
                        'message' => 'не найдено',
                        'raw_title' => 'хз',
                        'dates' => ['2026-08-14'],
                    ]]
                    : [],
                'date_from' => '2026-08-13',
                'date_to' => '2026-08-19',
                'applied' => false,
                'categories_applied' => [],
            ],
            'discarded' => [],
        ]);

        return $runId;
    }
}
