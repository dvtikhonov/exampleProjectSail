<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Food\BrisklySync\BrisklyCatalogGatewayInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncMatchOrchestratorInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncMatchRunStoreInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncSessionServiceInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncTokenCaptureGatewayInterface;
use App\DTO\Food\BrisklySync\BrisklyCategoryDto;
use App\DTO\Food\BrisklySync\BrisklyCreatedItemDto;
use App\DTO\Food\BrisklySync\BrisklySnapshotItemDto;
use App\DTO\Food\BrisklySync\MatchCandidateDto;
use App\DTO\Food\BrisklySync\MatchLineResultDto;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Exceptions\Food\FoodDomainException;
use App\Jobs\Food\RunBrisklySyncMatchJob;
use App\Models\Food\Dish;
use App\Models\Food\MenuCategory;
use App\Models\Food\Restaurant;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\FakeBrisklySyncMatchOrchestrator;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Внутренний колбэк POST match-complete (секрет + Form Request, без miniapp auth).
 */
class InternalBrisklySyncMatchCompleteApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string CALLBACK = '/api/food/internal/briskly-sync/match-complete';

    private const string ADMIN_BASE = '/api/food/admin/briskly-sync';

    private const string SECRET = 'internal-briskly-capture-secret';

    /** @var list<BrisklySnapshotItemDto> */
    public array $fakeSnapshot = [];

    /** @var list<BrisklyCategoryDto> */
    public array $fakeCategories = [];

    /** @var list<MatchLineResultDto> */
    public array $fakeMatchLines = [];

    /** @var list<array{op: string, payload: array<string, mixed>}> */
    public array $brisklyWrites = [];

    public bool $orchestratorDown = false;

    public bool $orchestratorDeferComplete = true;

    /** @var list<array{session_id: string, match_generation: string}> */
    public array $abortedMatchRuns = [];

    public string $fakeCaptureToken = 'secret-briskly-token-value';

    public ?FoodDomainException $captureFailure = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetFoodDomainTables();
        $this->fakeSnapshot = [];
        $this->fakeCategories = [];
        $this->fakeMatchLines = [];
        $this->brisklyWrites = [];
        $this->orchestratorDown = false;
        $this->orchestratorDeferComplete = true;
        $this->abortedMatchRuns = [];
        $this->fakeCaptureToken = 'secret-briskly-token-value';
        $this->captureFailure = null;
        config(['briskly_sync.capture_secret' => self::SECRET]);
        $this->bindFakes();
    }

    public function test_requires_capture_secret(): void
    {
        $this->postJson(self::CALLBACK, [
            'session_id' => (string) Str::uuid(),
            'match_generation' => (string) Str::uuid(),
            'match_lines' => [],
        ])->assertUnauthorized();

        $this->postJson(self::CALLBACK, [
            'session_id' => (string) Str::uuid(),
            'match_generation' => (string) Str::uuid(),
            'match_lines' => [],
        ], [
            'X-Briskly-Capture-Secret' => 'wrong',
        ])->assertUnauthorized();
    }

    public function test_rejects_when_both_or_neither_lines_and_error(): void
    {
        $payloadBase = [
            'session_id' => (string) Str::uuid(),
            'match_generation' => (string) Str::uuid(),
        ];

        $this->postJson(self::CALLBACK, $payloadBase, $this->secretHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['match_lines']);

        $this->postJson(self::CALLBACK, [
            ...$payloadBase,
            'match_lines' => [],
            'error' => 'boom',
        ], $this->secretHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['match_lines']);
    }

    public function test_complete_with_match_lines_sets_matched(): void
    {
        Queue::fake();
        [$sessionId, $generation, $linePayload] = $this->prepareMatchingSession();

        $this->postJson(self::CALLBACK, [
            'session_id' => $sessionId,
            'match_generation' => $generation,
            'match_lines' => [$linePayload],
            'raw_text' => '=== match_lines ===',
        ], $this->secretHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true);

        $manager = $this->maxManagerAuth(40_101);
        $this->getJson(self::ADMIN_BASE.'/sessions/'.$sessionId, $manager['headers'])
            ->assertOk()
            ->assertJsonPath('session.status', 'matched');
    }

    public function test_complete_with_error_sets_failed(): void
    {
        Queue::fake();
        [$sessionId, $generation] = $this->prepareMatchingSession();

        $this->postJson(self::CALLBACK, [
            'session_id' => $sessionId,
            'match_generation' => $generation,
            'error' => 'LLM watchdog timeout',
        ], $this->secretHeaders())
            ->assertOk()
            ->assertJsonPath('ok', true);

        $manager = $this->maxManagerAuth(40_102);
        $this->getJson(self::ADMIN_BASE.'/sessions/'.$sessionId, $manager['headers'])
            ->assertOk()
            ->assertJsonPath('session.status', 'failed');
    }

    public function test_stale_generation_returns_conflict(): void
    {
        Queue::fake();
        [$sessionId, $generation, $linePayload] = $this->prepareMatchingSession();

        $this->app->make(BrisklySyncSessionServiceInterface::class)
            ->expireQueuedMatch($sessionId, $generation);

        $this->postJson(self::CALLBACK, [
            'session_id' => $sessionId,
            'match_generation' => $generation,
            'match_lines' => [$linePayload],
        ], $this->secretHeaders())
            ->assertStatus(409)
            ->assertJsonPath('message', 'Устаревший callback match.');
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function prepareMatchingSession(): array
    {
        $manager = $this->maxManagerAuth(40_100);
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => false,
        ]);
        $dish = Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Борщ',
            'price' => 150,
            'is_available' => true,
        ]);

        $this->fakeSnapshot = [
            new BrisklySnapshotItemDto(501, 'Борщ', '140.00'),
        ];

        $lineKey = 'single:'.$dish->id;
        $linePayload = [
            'line_key' => $lineKey,
            'display_name' => 'Борщ',
            'compare_name' => 'борщ',
            'candidates' => [
                ['id' => 501, 'name' => 'Борщ'],
            ],
        ];
        $this->fakeMatchLines = [
            new MatchLineResultDto(
                $lineKey,
                'Борщ',
                'борщ',
                [new MatchCandidateDto(501, 'Борщ')],
            ),
        ];

        $sessionId = $this->postJson(self::ADMIN_BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
        ], $manager['headers'])->json('session.id');

        $this->postJson(self::ADMIN_BASE.'/sessions/'.$sessionId.'/snapshot', [], $manager['headers'])
            ->assertOk();
        $this->postJson(self::ADMIN_BASE.'/sessions/'.$sessionId.'/match', [], $manager['headers'])
            ->assertAccepted();

        $job = new RunBrisklySyncMatchJob($sessionId, 90);
        $job->handle($this->app->make(BrisklySyncSessionServiceInterface::class));

        $generation = $this->app->make(BrisklySyncMatchRunStoreInterface::class)->get($sessionId);
        $this->assertIsString($generation);

        return [$sessionId, $generation, $linePayload];
    }

    /**
     * @return array<string, string>
     */
    private function secretHeaders(): array
    {
        return ['X-Briskly-Capture-Secret' => self::SECRET];
    }

    private function bindFakes(): void
    {
        $test = $this;

        $this->app->instance(BrisklySyncTokenCaptureGatewayInterface::class, new class($test) implements BrisklySyncTokenCaptureGatewayInterface
        {
            public function __construct(private InternalBrisklySyncMatchCompleteApiTest $test) {}

            public function captureToken(): string
            {
                if ($this->test->captureFailure !== null) {
                    throw $this->test->captureFailure;
                }

                return $this->test->fakeCaptureToken;
            }
        });

        $this->app->instance(BrisklyCatalogGatewayInterface::class, new class($test) implements BrisklyCatalogGatewayInterface
        {
            public function __construct(private InternalBrisklySyncMatchCompleteApiTest $test) {}

            public function fetchSnapshot(string $token, ?string $searchText): array
            {
                return $this->test->fakeSnapshot;
            }

            public function listCategories(string $token): array
            {
                return $this->test->fakeCategories;
            }

            public function updateItemPrice(string $token, int $itemId, string $price): void
            {
                $this->test->brisklyWrites[] = [
                    'op' => 'update',
                    'payload' => ['item_id' => $itemId, 'price' => $price],
                ];
            }

            public function createItem(
                string $token,
                string $name,
                string $price,
                int $categoryId,
                int $catalogId,
            ): BrisklyCreatedItemDto {
                $this->test->brisklyWrites[] = [
                    'op' => 'create',
                    'payload' => [
                        'name' => $name,
                        'price' => $price,
                        'category_id' => $categoryId,
                        'catalog_id' => $catalogId,
                    ],
                ];

                return new BrisklyCreatedItemDto(
                    id: 900001 + count($this->test->brisklyWrites),
                    name: $name,
                    price: $price,
                    barcode: '2000000000001',
                    categoryId: $categoryId,
                    catalogId: $catalogId,
                    status: 1,
                    unitId: 796,
                );
            }
        });

        $this->app->instance(
            BrisklySyncMatchOrchestratorInterface::class,
            new FakeBrisklySyncMatchOrchestrator($test, $this->app),
        );
    }

    /**
     * @return array{user: MaxUser, headers: array<string, string>}
     */
    private function maxManagerAuth(int $maxUserId): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => $maxUserId,
                'first_name' => 'MaxManager'.$maxUserId,
            ])),
            FoodOrderAdminRole::MaxManager,
        );
    }
}
