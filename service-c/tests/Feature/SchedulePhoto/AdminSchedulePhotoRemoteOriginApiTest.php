<?php

declare(strict_types=1);

namespace Tests\Feature\SchedulePhoto;

use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Shared\HttpResponseDto;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Food\Dish;
use App\Models\Food\DishAvailabilityDate;
use App\Models\Food\MenuCategory;
use App\Models\Food\Restaurant;
use App\Models\Max\MaxUser;
use App\Modules\SchedulePhoto\Enums\SchedulePhotoRunStatus;
use App\Modules\SchedulePhoto\Models\SchedulePhotoRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FoodTestDataBuilder;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Feature: удалённый BASE_URL — match/apply уходят в HTTP, прогон пишется локально, меню нет.
 */
class AdminSchedulePhotoRemoteOriginApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string OCR_COMPLETE = '/api/food/internal/schedule-photo/ocr-complete';

    private const string REMOTE_ORIGIN = 'https://max-dev.94-228-117-27.sslip.io';

    private const string SECRET = 'schedule-photo-remote-origin-secret';

    private const string AGENT_TOKEN = 'schedule-photo-agent-token';

    private const string WRITE_TOKEN = 'schedule-photo-write-token';

    private const int REMOTE_RESTAURANT_ID = 501;

    private const int REMOTE_CATEGORY_ID = 77;

    private const int REMOTE_DISH_ID = 900;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();

        config([
            'phototext.agent_token' => self::AGENT_TOKEN,
            'phototext.write_token' => self::WRITE_TOKEN,
            'schedule_photo.capture_secret' => self::SECRET,
            'schedule_photo.phototext_probe_timeout_seconds' => 10,
            'schedule_photo.allowed_base_urls' => [
                'http://localhost:8083',
                'http://host.docker.internal:8083',
                self::REMOTE_ORIGIN,
            ],
        ]);
    }

    public function test_remote_match_and_apply_update_local_run_without_writing_local_menu(): void
    {
        $fixture = FoodTestDataBuilder::createRestaurantWithDish(
            'Локальная столовая',
            'Локальный борщ',
            120,
        );
        DishAvailabilityDate::query()->create([
            'dish_id' => $fixture['dish']->id,
            'available_date' => '2026-08-10',
        ]);
        $menuBefore = $this->localMenuSnapshot();

        $ownerId = 63_010;
        $menu = $this->menuManagerAuth($ownerId);
        $runId = (string) Str::uuid();
        SchedulePhotoRun::query()->create([
            'id' => $runId,
            'status' => SchedulePhotoRunStatus::OcrRunning,
            'base_url' => self::REMOTE_ORIGIN,
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'restaurant_id' => self::REMOTE_RESTAURANT_ID,
            'category_ids' => [self::REMOTE_CATEGORY_ID],
            'created_by_max_user_id' => $ownerId,
            'photo_path' => '/tmp/remote-schedule.jpg',
            'discarded' => [],
        ]);

        $http = $this->bindFakeHttp([
            '/api/food/phototext/schedule/match' => [
                'status' => 200,
                'body' => $this->schedulePayload(applied: false),
            ],
            '/api/food/phototext/schedule/apply' => [
                'status' => 200,
                'body' => $this->schedulePayload(applied: true, categoriesApplied: [self::REMOTE_CATEGORY_ID]),
            ],
            '/api/food/phototext/catalog?restaurant_id='.self::REMOTE_RESTAURANT_ID => [
                'status' => 200,
                'body' => [
                    'catalog' => [
                        'restaurant_id' => self::REMOTE_RESTAURANT_ID,
                        'restaurant_name' => 'Remote Столовая',
                        'categories' => [
                            [
                                'id' => self::REMOTE_CATEGORY_ID,
                                'name' => 'Remote Супы',
                                'dishes' => [
                                    ['id' => self::REMOTE_DISH_ID, 'name' => 'Удалённый борщ'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->postJson(self::OCR_COMPLETE, [
            'run_id' => $runId,
            'entries' => [
                ['raw_title' => 'Борщ', 'dates' => ['2026-08-13']],
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
            ->assertJsonPath('run.status', 'matched')
            ->assertJsonPath('run.base_url', self::REMOTE_ORIGIN)
            ->assertJsonPath('run.match_result.applied', false)
            ->assertJsonPath('run.match_result.matched_count', 1)
            ->assertJsonPath('run.match_result.matched.0.dish_id', self::REMOTE_DISH_ID)
            ->assertJsonPath('run.discarded.0.raw_title', 'хз');

        $matchCall = $http->calls[0] ?? null;
        $this->assertNotNull($matchCall);
        $this->assertSame('POST', $matchCall['method']);
        $this->assertSame('/api/food/phototext/schedule/match', $matchCall['url']);
        $this->assertSame(self::REMOTE_ORIGIN, $matchCall['baseUrl']);
        $this->assertSame(self::AGENT_TOKEN, $matchCall['headers']['X-PhotoText-Token'] ?? null);
        $this->assertArrayNotHasKey('X-PhotoText-Write-Token', $matchCall['headers']);
        $this->assertSame([
            'restaurant_id' => self::REMOTE_RESTAURANT_ID,
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'entries' => [
                ['name' => 'Борщ', 'dates' => ['2026-08-13']],
            ],
            'category_ids' => [self::REMOTE_CATEGORY_ID],
        ], $matchCall['jsonBody']);

        $this->assertDatabaseHas('schedule_photo_runs', [
            'id' => $runId,
            'status' => 'matched',
            'base_url' => self::REMOTE_ORIGIN,
            'restaurant_id' => self::REMOTE_RESTAURANT_ID,
        ]);
        $this->assertSame($menuBefore, $this->localMenuSnapshot());

        $this->postJson('/api/food/admin/schedule-photo/runs/'.$runId.'/apply', [], $menu['headers'])
            ->assertOk()
            ->assertJsonPath('status', 'applied')
            ->assertJsonPath('base_url', self::REMOTE_ORIGIN)
            ->assertJsonPath('match_result.applied', true)
            ->assertJsonPath('match_result.matched_count', 1)
            ->assertJsonPath('match_result.categories_applied', [self::REMOTE_CATEGORY_ID])
            ->assertJsonPath('catalog_scope.0.id', self::REMOTE_DISH_ID)
            ->assertJsonPath('catalog_scope.0.name', 'Удалённый борщ');

        $applyCall = $http->calls[1] ?? null;
        $catalogCall = $http->calls[2] ?? null;
        $this->assertNotNull($applyCall);
        $this->assertNotNull($catalogCall);
        $this->assertSame('POST', $applyCall['method']);
        $this->assertSame('/api/food/phototext/schedule/apply', $applyCall['url']);
        $this->assertSame(self::REMOTE_ORIGIN, $applyCall['baseUrl']);
        $this->assertSame(self::AGENT_TOKEN, $applyCall['headers']['X-PhotoText-Token'] ?? null);
        $this->assertSame(self::WRITE_TOKEN, $applyCall['headers']['X-PhotoText-Write-Token'] ?? null);
        $this->assertSame([
            'restaurant_id' => self::REMOTE_RESTAURANT_ID,
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'entries' => [
                ['name' => 'Удалённый борщ', 'dates' => ['2026-08-13']],
            ],
            'category_ids' => [self::REMOTE_CATEGORY_ID],
        ], $applyCall['jsonBody']);
        $this->assertSame('GET', $catalogCall['method']);
        $this->assertSame(
            '/api/food/phototext/catalog?restaurant_id='.self::REMOTE_RESTAURANT_ID,
            $catalogCall['url'],
        );
        $this->assertSame(self::REMOTE_ORIGIN, $catalogCall['baseUrl']);
        $this->assertArrayNotHasKey('X-PhotoText-Write-Token', $catalogCall['headers']);
        $this->assertCount(3, $http->calls);

        $this->assertDatabaseHas('schedule_photo_runs', [
            'id' => $runId,
            'status' => 'applied',
            'base_url' => self::REMOTE_ORIGIN,
        ]);
        $stored = SchedulePhotoRun::query()->findOrFail($runId);
        $this->assertTrue($stored->match_result['applied'] ?? false);
        $this->assertSame([self::REMOTE_CATEGORY_ID], $stored->match_result['categories_applied'] ?? null);
        $this->assertSame('хз', $stored->discarded[0]['raw_title'] ?? null);

        $this->assertSame($menuBefore, $this->localMenuSnapshot());
        $this->assertDatabaseMissing('max_restaurants', ['id' => self::REMOTE_RESTAURANT_ID]);
        $this->assertDatabaseMissing('max_menu_categories', ['id' => self::REMOTE_CATEGORY_ID]);
        $this->assertDatabaseMissing('max_dishes', ['id' => self::REMOTE_DISH_ID]);
        $this->assertSame('Локальная столовая', $fixture['restaurant']->fresh()?->name);
        $this->assertSame('Локальный борщ', $fixture['dish']->fresh()?->name);
        $this->assertDatabaseHas('max_dish_availability_dates', [
            'dish_id' => $fixture['dish']->id,
            'available_date' => '2026-08-10',
        ]);
    }

    /**
     * @return array{
     *     restaurants: int,
     *     categories: int,
     *     dishes: int,
     *     dates: int
     * }
     */
    private function localMenuSnapshot(): array
    {
        return [
            'restaurants' => Restaurant::query()->count(),
            'categories' => MenuCategory::query()->count(),
            'dishes' => Dish::query()->count(),
            'dates' => DishAvailabilityDate::query()->count(),
        ];
    }

    /**
     * @param  list<int>  $categoriesApplied
     * @return array<string, mixed>
     */
    private function schedulePayload(bool $applied, array $categoriesApplied = []): array
    {
        return [
            'matched_count' => 1,
            'matched' => [
                [
                    'raw_title' => 'Борщ',
                    'dish_id' => self::REMOTE_DISH_ID,
                    'dish_name' => 'Удалённый борщ',
                    'category_id' => self::REMOTE_CATEGORY_ID,
                    'category_name' => 'Remote Супы',
                    'dates' => ['2026-08-13'],
                ],
            ],
            'issues' => [],
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'applied' => $applied,
            'categories_applied' => $categoriesApplied,
        ];
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
     * @param  array<string, array{status: int, body: array<string, mixed>|string, successful?: bool}>  $routes
     * @return object{
     *     calls: list<array{
     *         method: string,
     *         url: string,
     *         headers: array<string, string>,
     *         jsonBody: array<string, mixed>|null,
     *         baseUrl: ?string
     *     }>
     * }&HttpClientInterface
     */
    private function bindFakeHttp(array $routes): HttpClientInterface
    {
        $http = new class($routes) implements HttpClientInterface
        {
            /**
             * @var list<array{
             *     method: string,
             *     url: string,
             *     headers: array<string, string>,
             *     jsonBody: array<string, mixed>|null,
             *     baseUrl: ?string
             * }>
             */
            public array $calls = [];

            /**
             * @param  array<string, array{status: int, body: array<string, mixed>|string, successful?: bool}>  $routes
             */
            public function __construct(private readonly array $routes) {}

            public function request(
                string $method,
                string $url,
                array $headers = [],
                ?array $jsonBody = null,
                ?string $baseUrl = null,
                int $timeoutSeconds = 30,
            ): HttpResponseDto {
                $this->calls[] = [
                    'method' => $method,
                    'url' => $url,
                    'headers' => $headers,
                    'jsonBody' => $jsonBody,
                    'baseUrl' => $baseUrl,
                ];

                $route = $this->routes[$url] ?? null;
                if ($route === null) {
                    return new HttpResponseDto(
                        status: 404,
                        body: json_encode(['message' => 'not stubbed: '.$url], JSON_THROW_ON_ERROR),
                        successful: false,
                    );
                }

                $status = $route['status'];
                $successful = $route['successful'] ?? ($status >= 200 && $status < 300);
                $body = $route['body'];
                $encoded = is_string($body)
                    ? $body
                    : json_encode($body, JSON_THROW_ON_ERROR);

                return new HttpResponseDto(status: $status, body: $encoded, successful: $successful);
            }
        };

        $this->app->instance(HttpClientInterface::class, $http);

        return $http;
    }
}
