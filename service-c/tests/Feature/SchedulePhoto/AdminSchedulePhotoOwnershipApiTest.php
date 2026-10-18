<?php

declare(strict_types=1);

namespace Tests\Feature\SchedulePhoto;

use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Max\MaxUser;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasDictionaryInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoAliasesWriterInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoCatalogScopeBuilderInterface;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoCatalogScopeDishDto;
use App\Modules\SchedulePhoto\Domain\Enum\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Infrastructure\Persistence\Eloquent\SchedulePhotoRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Чужой menu_manager не отличает чужой прогон от отсутствующего:
 * GET show, POST apply, POST resolve-discarded.
 */
class AdminSchedulePhotoOwnershipApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string NOT_FOUND_MESSAGE = 'Прогон SchedulePhoto не найден.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();
    }

    public function test_show_hides_foreign_run_with_same_message_as_missing(): void
    {
        $ownerId = 71_040;
        $stranger = $this->menuManagerAuth(71_041);
        $runId = $this->createMatchedRun($ownerId);
        $missingId = (string) Str::uuid();

        $missing = $this->getJson($this->showEndpoint($missingId), $stranger['headers']);
        $foreign = $this->getJson($this->showEndpoint($runId), $stranger['headers']);

        $this->assertSameNotFoundMessage($missing, $foreign);
        $this->assertRunUntouched($runId, $ownerId);
    }

    public function test_apply_hides_foreign_run_and_does_not_write_schedule(): void
    {
        $ownerId = 71_042;
        $stranger = $this->menuManagerAuth(71_043);
        $runId = $this->createMatchedRun($ownerId);
        $missingId = (string) Str::uuid();

        $this->mock(SchedulePhotoPhotoTextClientInterface::class, function ($mock): void {
            $mock->shouldReceive('apply')->never();
        });

        $missing = $this->postJson($this->applyEndpoint($missingId), [], $stranger['headers']);
        $foreign = $this->postJson($this->applyEndpoint($runId), [], $stranger['headers']);

        $this->assertSameNotFoundMessage($missing, $foreign);
        $this->assertRunUntouched($runId, $ownerId);
    }

    public function test_resolve_discarded_hides_foreign_run_and_does_not_write_aliases(): void
    {
        $ownerId = 71_044;
        $stranger = $this->menuManagerAuth(71_045);
        $runId = $this->createMatchedRun($ownerId, discarded: [
            [
                'raw_title' => 'пюрэшка',
                'reason' => 'dish_not_found',
                'dates' => ['2026-08-13'],
            ],
        ]);
        $missingId = (string) Str::uuid();
        $payload = [
            'raw_title' => 'пюрэшка',
            'dates' => ['2026-08-13'],
            'dish_name' => 'Пюре',
        ];

        $this->mock(SchedulePhotoAliasDictionaryInterface::class, function ($mock): void {
            $mock->shouldReceive('normalizeKey')->zeroOrMoreTimes()->andReturnUsing(
                static fn (string $value): string => mb_strtolower(trim($value), 'UTF-8'),
            );
            $mock->shouldReceive('resolveCanon')->zeroOrMoreTimes()->andReturn(null);
        });

        $this->mock(SchedulePhotoCatalogScopeBuilderInterface::class, function ($mock): void {
            $mock->shouldReceive('build')->zeroOrMoreTimes()->andReturn([
                new SchedulePhotoCatalogScopeDishDto(
                    id: 10,
                    name: 'Пюре',
                    categoryId: 2,
                    categoryName: 'Гарнир',
                ),
            ]);
        });

        $this->mock(SchedulePhotoAliasesWriterInterface::class, function ($mock): void {
            $mock->shouldReceive('upsertPair')->never();
        });

        $missing = $this->postJson($this->resolveEndpoint($missingId), $payload, $stranger['headers']);
        $foreign = $this->postJson($this->resolveEndpoint($runId), $payload, $stranger['headers']);

        $this->assertSameNotFoundMessage($missing, $foreign);
        $this->assertRunUntouched($runId, $ownerId, [
            [
                'raw_title' => 'пюрэшка',
                'reason' => 'dish_not_found',
                'dates' => ['2026-08-13'],
            ],
        ]);
    }

    private function assertSameNotFoundMessage(TestResponse $missing, TestResponse $foreign): void
    {
        $missing->assertNotFound()->assertJsonPath('message', self::NOT_FOUND_MESSAGE);
        $foreign->assertNotFound()->assertJsonPath('message', self::NOT_FOUND_MESSAGE);
        $this->assertSame($missing->json('message'), $foreign->json('message'));
    }

    /**
     * @param  list<array{raw_title: string, reason: string, dates?: list<string>}>  $discarded
     */
    private function assertRunUntouched(string $runId, int $ownerId, array $discarded = []): void
    {
        $run = SchedulePhotoRun::query()->findOrFail($runId);

        $this->assertSame(SchedulePhotoRunStatus::Matched, $run->status);
        $this->assertSame($ownerId, $run->created_by_max_user_id);
        $this->assertEquals($discarded, $run->discarded);
        $this->assertFalse((bool) ($run->match_result['applied'] ?? false));
    }

    private function showEndpoint(string $runId): string
    {
        return '/api/food/admin/schedule-photo/runs/'.$runId;
    }

    private function applyEndpoint(string $runId): string
    {
        return '/api/food/admin/schedule-photo/runs/'.$runId.'/apply';
    }

    private function resolveEndpoint(string $runId): string
    {
        return '/api/food/admin/schedule-photo/runs/'.$runId.'/resolve-discarded';
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
     * @param  list<array{raw_title: string, reason: string, dates?: list<string>}>  $discarded
     */
    private function createMatchedRun(int $createdByMaxUserId, array $discarded = []): string
    {
        $runId = (string) Str::uuid();

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
            ],
            'discarded' => $discarded,
        ]);

        return $runId;
    }
}
