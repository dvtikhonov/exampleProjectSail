<?php

declare(strict_types=1);

namespace Tests\Feature\SchedulePhoto;

use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Max\MaxUser;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoCatalogScopeBuilderInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoCatalogScopeDishDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleEntryDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleMatchedDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleResultDto;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasDictionaryInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasesWriterInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;
use App\Modules\SchedulePhoto\Infrastructure\Persistence\Eloquent\SchedulePhotoRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Feature: POST /api/food/admin/schedule-photo/runs/{id}/resolve-discarded.
 */
class AdminSchedulePhotoResolveDiscardedApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    public function test_resolves_discarded_via_alias_suggestion(): void
    {
        $menu = $this->menuManagerAuth(71_020);
        $runId = (string) Str::uuid();
        $canon = 'Салат с ветчиной (ветчина, яйцо, свежий огурец, сыр, майонез)';

        SchedulePhotoRun::query()->create([
            'id' => $runId,
            'status' => SchedulePhotoRunStatus::Matched,
            'base_url' => 'http://localhost:8083',
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'restaurant_id' => 1,
            'category_ids' => [2],
            'created_by_max_user_id' => 71_020,
            'photo_path' => '/tmp/photo.jpg',
            'match_result' => [
                'matched_count' => 0,
                'matched' => [],
                'issues' => [],
                'date_from' => '2026-08-13',
                'date_to' => '2026-08-19',
                'applied' => false,
                'categories_applied' => [],
            ],
            'discarded' => [
                ['raw_title' => 'Ветчинный', 'reason' => 'dish_not_found'],
            ],
        ]);

        $this->mock(SchedulePhotoAliasDictionaryInterface::class, function ($mock) use ($canon): void {
            $mock->shouldReceive('resolveCanon')
                ->with('Ветчинный')
                ->andReturn($canon);
            $mock->shouldReceive('normalizeKey')->andReturnUsing(
                static fn (string $v): string => mb_strtolower(trim($v), 'UTF-8'),
            );
        });

        $this->mock(SchedulePhotoCatalogScopeBuilderInterface::class, function ($mock) use ($canon): void {
            $mock->shouldReceive('build')->andReturn([
                new SchedulePhotoCatalogScopeDishDto(
                    id: 10,
                    name: $canon,
                    categoryId: 2,
                    categoryName: 'Салаты',
                ),
            ]);
        });

        $this->mock(SchedulePhotoPhotoTextClientInterface::class, function ($mock) use ($canon): void {
            $mock->shouldReceive('match')->once()->andReturn(new SchedulePhotoScheduleResultDto(
                matchedCount: 1,
                matched: [
                    new SchedulePhotoScheduleMatchedDto(
                        rawTitle: $canon,
                        dishId: 10,
                        dishName: $canon,
                        categoryId: 2,
                        categoryName: 'Салаты',
                        dates: ['2026-08-13'],
                    ),
                ],
                issues: [],
                dateFrom: '2026-08-13',
                dateTo: '2026-08-19',
            ));
        });

        $this->postJson("/api/food/admin/schedule-photo/runs/{$runId}/resolve-discarded", [
            'raw_title' => 'Ветчинный',
            'dates' => ['2026-08-13'],
        ], $menu['headers'])
            ->assertOk()
            ->assertJsonPath('status', 'matched')
            ->assertJsonPath('match_result.matched_count', 1)
            ->assertJsonPath('discarded', []);
    }

    public function test_requires_dish_name_when_alias_missing(): void
    {
        $menu = $this->menuManagerAuth(71_021);
        $runId = (string) Str::uuid();

        SchedulePhotoRun::query()->create([
            'id' => $runId,
            'status' => SchedulePhotoRunStatus::Matched,
            'base_url' => 'http://localhost:8083',
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'restaurant_id' => 1,
            'category_ids' => [2],
            'created_by_max_user_id' => 71_021,
            'photo_path' => '/tmp/photo.jpg',
            'match_result' => [
                'matched_count' => 0,
                'matched' => [],
                'issues' => [],
                'date_from' => '2026-08-13',
                'date_to' => '2026-08-19',
                'applied' => false,
                'categories_applied' => [],
            ],
            'discarded' => [
                ['raw_title' => 'хз', 'reason' => 'dish_not_found'],
            ],
        ]);

        $this->mock(SchedulePhotoAliasDictionaryInterface::class, function ($mock): void {
            $mock->shouldReceive('resolveCanon')->andReturn(null);
            $mock->shouldReceive('normalizeKey')->andReturnUsing(
                static fn (string $v): string => mb_strtolower(trim($v), 'UTF-8'),
            );
        });

        $this->mock(SchedulePhotoCatalogScopeBuilderInterface::class, function ($mock): void {
            $mock->shouldReceive('build')->andReturn([
                new SchedulePhotoCatalogScopeDishDto(
                    id: 10,
                    name: 'Пюре',
                    categoryId: 2,
                    categoryName: 'Гарнир',
                ),
            ]);
        });

        $this->mock(SchedulePhotoPhotoTextClientInterface::class, function ($mock): void {
            $mock->shouldReceive('match')->never();
        });

        $this->postJson("/api/food/admin/schedule-photo/runs/{$runId}/resolve-discarded", [
            'raw_title' => 'хз',
            'dates' => ['2026-08-13'],
        ], $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn ($message): bool => is_string($message)
                && str_contains($message, 'Нет канона'));
    }

    public function test_saves_alias_when_dish_selected_manually(): void
    {
        $menu = $this->menuManagerAuth(71_022);
        $runId = (string) Str::uuid();
        $canon = 'Пюре';

        SchedulePhotoRun::query()->create([
            'id' => $runId,
            'status' => SchedulePhotoRunStatus::Matched,
            'base_url' => 'http://localhost:8083',
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'restaurant_id' => 1,
            'category_ids' => [2],
            'created_by_max_user_id' => 71_022,
            'photo_path' => '/tmp/photo.jpg',
            'match_result' => [
                'matched_count' => 0,
                'matched' => [],
                'issues' => [],
                'date_from' => '2026-08-13',
                'date_to' => '2026-08-19',
                'applied' => false,
                'categories_applied' => [],
            ],
            'discarded' => [
                ['raw_title' => 'пюрэшка', 'reason' => 'dish_not_found'],
            ],
        ]);

        $this->mock(SchedulePhotoAliasDictionaryInterface::class, function ($mock): void {
            $mock->shouldReceive('resolveCanon')->andReturn(null);
            $mock->shouldReceive('normalizeKey')->andReturnUsing(
                static fn (string $v): string => mb_strtolower(trim($v), 'UTF-8'),
            );
        });

        $this->mock(SchedulePhotoAliasesWriterInterface::class, function ($mock) use ($canon): void {
            $mock->shouldReceive('upsertPair')
                ->once()
                ->with('пюрэшка', $canon)
                ->andReturn(true);
        });

        $this->mock(SchedulePhotoCatalogScopeBuilderInterface::class, function ($mock) use ($canon): void {
            $mock->shouldReceive('build')->andReturn([
                new SchedulePhotoCatalogScopeDishDto(
                    id: 10,
                    name: $canon,
                    categoryId: 2,
                    categoryName: 'Гарнир',
                ),
            ]);
        });

        $this->mock(SchedulePhotoPhotoTextClientInterface::class, function ($mock) use ($canon): void {
            $mock->shouldReceive('match')->once()->andReturn(new SchedulePhotoScheduleResultDto(
                matchedCount: 1,
                matched: [
                    new SchedulePhotoScheduleMatchedDto(
                        rawTitle: $canon,
                        dishId: 10,
                        dishName: $canon,
                        categoryId: 2,
                        categoryName: 'Гарнир',
                        dates: ['2026-08-13'],
                    ),
                ],
                issues: [],
                dateFrom: '2026-08-13',
                dateTo: '2026-08-19',
            ));
        });

        $this->postJson("/api/food/admin/schedule-photo/runs/{$runId}/resolve-discarded", [
            'raw_title' => 'пюрэшка',
            'dates' => ['2026-08-13'],
            'dish_name' => $canon,
        ], $menu['headers'])
            ->assertOk()
            ->assertJsonPath('status', 'matched')
            ->assertJsonPath('discarded', []);
    }

    public function test_upserts_alias_when_existing_out_of_scope(): void
    {
        $menu = $this->menuManagerAuth(71_025);
        $runId = (string) Str::uuid();
        $ocr = 'Курица с черносливом';
        $oldCanon = 'Куриное филе с черносливом';
        $newCanon = 'Салат с курицей и черносливом';

        SchedulePhotoRun::query()->create([
            'id' => $runId,
            'status' => SchedulePhotoRunStatus::Matched,
            'base_url' => 'http://localhost:8083',
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'restaurant_id' => 1,
            'category_ids' => [2],
            'created_by_max_user_id' => 71_025,
            'photo_path' => '/tmp/photo.jpg',
            'match_result' => [
                'matched_count' => 0,
                'matched' => [],
                'issues' => [],
                'date_from' => '2026-08-13',
                'date_to' => '2026-08-19',
                'applied' => false,
                'categories_applied' => [],
            ],
            'discarded' => [
                ['raw_title' => $ocr, 'reason' => 'dish_not_found', 'dates' => ['2026-08-13']],
            ],
        ]);

        $this->mock(SchedulePhotoAliasDictionaryInterface::class, function ($mock) use ($ocr, $oldCanon): void {
            $mock->shouldReceive('resolveCanon')->with($ocr)->andReturn($oldCanon);
            $mock->shouldReceive('normalizeKey')->andReturnUsing(
                static fn (string $v): string => mb_strtolower(trim($v), 'UTF-8'),
            );
        });

        $this->mock(SchedulePhotoAliasesWriterInterface::class, function ($mock) use ($ocr, $newCanon): void {
            $mock->shouldReceive('upsertPair')
                ->once()
                ->with($ocr, $newCanon)
                ->andReturn(true);
        });

        $this->mock(SchedulePhotoCatalogScopeBuilderInterface::class, function ($mock) use ($newCanon): void {
            $mock->shouldReceive('build')->andReturn([
                new SchedulePhotoCatalogScopeDishDto(
                    id: 20,
                    name: $newCanon,
                    categoryId: 2,
                    categoryName: 'Салаты',
                ),
            ]);
        });

        $this->mock(SchedulePhotoPhotoTextClientInterface::class, function ($mock) use ($newCanon): void {
            $mock->shouldReceive('match')->once()->andReturn(new SchedulePhotoScheduleResultDto(
                matchedCount: 1,
                matched: [
                    new SchedulePhotoScheduleMatchedDto(
                        rawTitle: $newCanon,
                        dishId: 20,
                        dishName: $newCanon,
                        categoryId: 2,
                        categoryName: 'Салаты',
                        dates: ['2026-08-13'],
                    ),
                ],
                issues: [],
                dateFrom: '2026-08-13',
                dateTo: '2026-08-19',
            ));
        });

        $this->postJson("/api/food/admin/schedule-photo/runs/{$runId}/resolve-discarded", [
            'raw_title' => $ocr,
            'dates' => ['2026-08-13'],
            'dish_name' => $newCanon,
        ], $menu['headers'])
            ->assertOk()
            ->assertJsonPath('discarded', []);
    }

    public function test_resolve_rejects_whole_window_when_photo_dates_missing(): void
    {
        $menu = $this->menuManagerAuth(71_024);
        $runId = (string) Str::uuid();

        SchedulePhotoRun::query()->create([
            'id' => $runId,
            'status' => SchedulePhotoRunStatus::Matched,
            'base_url' => 'http://localhost:8083',
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'restaurant_id' => 1,
            'category_ids' => [2],
            'created_by_max_user_id' => 71_024,
            'photo_path' => '/tmp/photo.jpg',
            'match_result' => [
                'matched_count' => 0,
                'matched' => [],
                'issues' => [],
                'date_from' => '2026-08-13',
                'date_to' => '2026-08-19',
                'applied' => false,
                'categories_applied' => [],
            ],
            'discarded' => [
                ['raw_title' => 'пюрэшка', 'reason' => 'dish_not_found'],
            ],
        ]);

        $this->mock(SchedulePhotoAliasDictionaryInterface::class, function ($mock): void {
            $mock->shouldReceive('resolveCanon')->andReturn(null);
            $mock->shouldReceive('normalizeKey')->andReturnUsing(
                static fn (string $v): string => mb_strtolower(trim($v), 'UTF-8'),
            );
        });

        $this->mock(SchedulePhotoCatalogScopeBuilderInterface::class, function ($mock): void {
            $mock->shouldReceive('build')->andReturn([
                new SchedulePhotoCatalogScopeDishDto(
                    id: 10,
                    name: 'Пюре',
                    categoryId: 2,
                    categoryName: 'Гарнир',
                ),
            ]);
        });

        $this->mock(SchedulePhotoPhotoTextClientInterface::class, function ($mock): void {
            $mock->shouldReceive('match')->never();
        });

        $this->postJson("/api/food/admin/schedule-photo/runs/{$runId}/resolve-discarded", [
            'raw_title' => 'пюрэшка',
            'dates' => [
                '2026-08-13',
                '2026-08-14',
                '2026-08-15',
                '2026-08-16',
                '2026-08-17',
                '2026-08-18',
                '2026-08-19',
            ],
            'dish_name' => 'Пюре',
        ], $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn ($message): bool => is_string($message)
                && str_contains($message, 'день заголовка'));
    }

    public function test_resolve_keeps_photo_dates_when_request_sends_whole_window(): void
    {
        $menu = $this->menuManagerAuth(71_023);
        $runId = (string) Str::uuid();
        $canon = 'Пюре';

        SchedulePhotoRun::query()->create([
            'id' => $runId,
            'status' => SchedulePhotoRunStatus::Matched,
            'base_url' => 'http://localhost:8083',
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'restaurant_id' => 1,
            'category_ids' => [2],
            'created_by_max_user_id' => 71_023,
            'photo_path' => '/tmp/photo.jpg',
            'match_result' => [
                'matched_count' => 0,
                'matched' => [],
                'issues' => [],
                'date_from' => '2026-08-13',
                'date_to' => '2026-08-19',
                'applied' => false,
                'categories_applied' => [],
            ],
            'discarded' => [
                [
                    'raw_title' => 'пюрэшка',
                    'reason' => 'dish_not_found',
                    'dates' => ['2026-08-15'],
                ],
            ],
        ]);

        $this->mock(SchedulePhotoAliasDictionaryInterface::class, function ($mock): void {
            $mock->shouldReceive('resolveCanon')->andReturn(null);
            $mock->shouldReceive('normalizeKey')->andReturnUsing(
                static fn (string $v): string => mb_strtolower(trim($v), 'UTF-8'),
            );
        });

        $this->mock(SchedulePhotoAliasesWriterInterface::class, function ($mock) use ($canon): void {
            $mock->shouldReceive('upsertPair')
                ->once()
                ->with('пюрэшка', $canon)
                ->andReturn(true);
        });

        $this->mock(SchedulePhotoCatalogScopeBuilderInterface::class, function ($mock) use ($canon): void {
            $mock->shouldReceive('build')->andReturn([
                new SchedulePhotoCatalogScopeDishDto(
                    id: 10,
                    name: $canon,
                    categoryId: 2,
                    categoryName: 'Гарнир',
                ),
            ]);
        });

        $this->mock(SchedulePhotoPhotoTextClientInterface::class, function ($mock) use ($canon): void {
            $mock->shouldReceive('match')
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
                    unset($restaurantId, $categoryIds, $dateFrom, $dateTo);
                    if (count($entries) !== 1 || ! $entries[0] instanceof SchedulePhotoScheduleEntryDto) {
                        return false;
                    }

                    return $entries[0]->dates === ['2026-08-15'];
                })
                ->andReturn(new SchedulePhotoScheduleResultDto(
                    matchedCount: 1,
                    matched: [
                        new SchedulePhotoScheduleMatchedDto(
                            rawTitle: $canon,
                            dishId: 10,
                            dishName: $canon,
                            categoryId: 2,
                            categoryName: 'Гарнир',
                            dates: ['2026-08-15'],
                        ),
                    ],
                    issues: [],
                    dateFrom: '2026-08-13',
                    dateTo: '2026-08-19',
                ));
        });

        $this->postJson("/api/food/admin/schedule-photo/runs/{$runId}/resolve-discarded", [
            'raw_title' => 'пюрэшка',
            'dates' => [
                '2026-08-13',
                '2026-08-14',
                '2026-08-15',
                '2026-08-16',
                '2026-08-17',
                '2026-08-18',
                '2026-08-19',
            ],
            'dish_name' => $canon,
        ], $menu['headers'])
            ->assertOk()
            ->assertJsonPath('match_result.matched.0.dates', ['2026-08-15']);
    }

    public function test_soft_fails_alias_writer_and_returns_alias_persist_error(): void
    {
        $menu = $this->menuManagerAuth(71_027);
        $runId = (string) Str::uuid();
        $canon = 'Пюре';

        SchedulePhotoRun::query()->create([
            'id' => $runId,
            'status' => SchedulePhotoRunStatus::Matched,
            'base_url' => 'http://localhost:8083',
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'restaurant_id' => 1,
            'category_ids' => [2],
            'created_by_max_user_id' => 71_027,
            'photo_path' => '/tmp/photo.jpg',
            'match_result' => [
                'matched_count' => 0,
                'matched' => [],
                'issues' => [],
                'date_from' => '2026-08-13',
                'date_to' => '2026-08-19',
                'applied' => false,
                'categories_applied' => [],
            ],
            'discarded' => [
                ['raw_title' => 'пюрэшка', 'reason' => 'dish_not_found', 'dates' => ['2026-08-13']],
            ],
        ]);

        $this->mock(SchedulePhotoAliasDictionaryInterface::class, function ($mock): void {
            $mock->shouldReceive('resolveCanon')->andReturn(null);
            $mock->shouldReceive('normalizeKey')->andReturnUsing(
                static fn (string $v): string => mb_strtolower(trim($v), 'UTF-8'),
            );
        });

        $this->mock(SchedulePhotoAliasesWriterInterface::class, function ($mock) use ($canon): void {
            $mock->shouldReceive('upsertPair')
                ->once()
                ->with('пюрэшка', $canon)
                ->andThrow(new \RuntimeException('disk full'));
        });

        $this->mock(SchedulePhotoCatalogScopeBuilderInterface::class, function ($mock) use ($canon): void {
            $mock->shouldReceive('build')->andReturn([
                new SchedulePhotoCatalogScopeDishDto(
                    id: 10,
                    name: $canon,
                    categoryId: 2,
                    categoryName: 'Гарнир',
                ),
            ]);
        });

        $this->mock(SchedulePhotoPhotoTextClientInterface::class, function ($mock) use ($canon): void {
            $mock->shouldReceive('match')->once()->andReturn(new SchedulePhotoScheduleResultDto(
                matchedCount: 1,
                matched: [
                    new SchedulePhotoScheduleMatchedDto(
                        rawTitle: $canon,
                        dishId: 10,
                        dishName: $canon,
                        categoryId: 2,
                        categoryName: 'Гарнир',
                        dates: ['2026-08-13'],
                    ),
                ],
                issues: [],
                dateFrom: '2026-08-13',
                dateTo: '2026-08-19',
            ));
        });

        $this->postJson("/api/food/admin/schedule-photo/runs/{$runId}/resolve-discarded", [
            'raw_title' => 'пюрэшка',
            'dates' => ['2026-08-13'],
            'dish_name' => $canon,
        ], $menu['headers'])
            ->assertOk()
            ->assertJsonPath('status', 'matched')
            ->assertJsonPath('discarded', [])
            ->assertJsonPath('alias_persist_error', fn ($message): bool => is_string($message)
                && str_contains($message, 'не удалось сохранить пару')
                && str_contains($message, 'disk full'));

        $run = SchedulePhotoRun::query()->findOrFail($runId);
        $this->assertSame(SchedulePhotoRunStatus::Matched, $run->status);
        $this->assertSame([], $run->discarded ?? []);
    }

    public function test_does_not_write_alias_when_match_fails(): void
    {
        $menu = $this->menuManagerAuth(71_026);
        $runId = (string) Str::uuid();
        $canon = 'Пюре';

        SchedulePhotoRun::query()->create([
            'id' => $runId,
            'status' => SchedulePhotoRunStatus::Matched,
            'base_url' => 'http://localhost:8083',
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'restaurant_id' => 1,
            'category_ids' => [2],
            'created_by_max_user_id' => 71_026,
            'photo_path' => '/tmp/photo.jpg',
            'match_result' => [
                'matched_count' => 0,
                'matched' => [],
                'issues' => [],
                'date_from' => '2026-08-13',
                'date_to' => '2026-08-19',
                'applied' => false,
                'categories_applied' => [],
            ],
            'discarded' => [
                ['raw_title' => 'пюрэшка', 'reason' => 'dish_not_found', 'dates' => ['2026-08-13']],
            ],
        ]);

        $this->mock(SchedulePhotoAliasDictionaryInterface::class, function ($mock): void {
            $mock->shouldReceive('resolveCanon')->andReturn(null);
            $mock->shouldReceive('normalizeKey')->andReturnUsing(
                static fn (string $v): string => mb_strtolower(trim($v), 'UTF-8'),
            );
        });

        $this->mock(SchedulePhotoAliasesWriterInterface::class, function ($mock): void {
            $mock->shouldReceive('upsertPair')->never();
        });

        $this->mock(SchedulePhotoCatalogScopeBuilderInterface::class, function ($mock) use ($canon): void {
            $mock->shouldReceive('build')->andReturn([
                new SchedulePhotoCatalogScopeDishDto(
                    id: 10,
                    name: $canon,
                    categoryId: 2,
                    categoryName: 'Гарнир',
                ),
            ]);
        });

        $this->mock(SchedulePhotoPhotoTextClientInterface::class, function ($mock): void {
            $mock->shouldReceive('match')
                ->once()
                ->andThrow(new SchedulePhotoDomainException('Сверка графика недоступна.', 503));
        });

        $this->postJson("/api/food/admin/schedule-photo/runs/{$runId}/resolve-discarded", [
            'raw_title' => 'пюрэшка',
            'dates' => ['2026-08-13'],
            'dish_name' => $canon,
        ], $menu['headers'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Сверка графика недоступна.');

        $run = SchedulePhotoRun::query()->findOrFail($runId);
        $this->assertSame(SchedulePhotoRunStatus::Matched, $run->status);
        $this->assertSame('пюрэшка', $run->discarded[0]['raw_title'] ?? null);
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
}
