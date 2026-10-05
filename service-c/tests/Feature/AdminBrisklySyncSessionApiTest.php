<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Food\BrisklySync\BrisklyCatalogGatewayInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncMatchOrchestratorInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncTokenCaptureGatewayInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncTokenStoreInterface;
use App\DTO\Food\BrisklySync\BrisklyCategoryDto;
use App\DTO\Food\BrisklySync\BrisklyCreatedItemDto;
use App\DTO\Food\BrisklySync\BrisklySnapshotItemDto;
use App\DTO\Food\BrisklySync\BrisklySyncLlmCallContextDto;
use App\DTO\Food\BrisklySync\MatchCandidateDto;
use App\DTO\Food\BrisklySync\MatchLineResultDto;
use App\DTO\Food\ComboCatalog\ComboCatalogPromptDto;
use App\Enums\Food\Menu\DishWeightUnit;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Food\BrisklySyncSession;
use App\Models\Food\Dish;
use App\Models\Food\MenuCategory;
use App\Models\Food\Restaurant;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

class AdminBrisklySyncSessionApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string BASE = '/api/food/admin/briskly-sync';

    /** @var list<BrisklySnapshotItemDto> */
    public array $fakeSnapshot = [];

    /** @var list<BrisklyCategoryDto> */
    public array $fakeCategories = [];

    /** @var list<MatchLineResultDto> */
    public array $fakeMatchLines = [];

    /** @var list<array{op: string, payload: array<string, mixed>}> */
    public array $brisklyWrites = [];

    public bool $orchestratorDown = false;

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
        $this->fakeCaptureToken = 'secret-briskly-token-value';
        $this->captureFailure = null;
        $this->bindFakes();
    }

    public function test_sessions_require_auth_and_max_manager(): void
    {
        $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => 1,
        ])->assertUnauthorized();

        $auth = $this->authenticateMaxUser();
        $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => 1,
        ], $auth['headers'])->assertForbidden();

        $menu = $this->asFoodOrderAdmin($auth, FoodOrderAdminRole::MenuManager);
        $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => 1,
        ], $menu['headers'])->assertForbidden();
    }

    public function test_create_session_snapshot_match_sync_results_without_token_leak(): void
    {
        $manager = $this->maxManagerAuth(40_001);
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => false,
        ]);
        $dishA = Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Борщ',
            'price' => 150,
            'is_available' => true,
        ]);
        $dishB = Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Только VPS',
            'price' => 80,
            'is_available' => true,
        ]);

        $this->fakeSnapshot = [
            new BrisklySnapshotItemDto(501, 'Борщ', '140.00'),
            new BrisklySnapshotItemDto(999, 'Только Briskly', '10.00'),
        ];
        $this->fakeMatchLines = [
            new MatchLineResultDto(
                'single:'.$dishA->id,
                'Борщ',
                'борщ',
                [new MatchCandidateDto(501, 'Борщ')],
            ),
            new MatchLineResultDto(
                'single:'.$dishB->id,
                'Только VPS',
                'только vps',
                [],
            ),
        ];

        $create = $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
            'clarification' => 'игнорировать вес',
            'vps_category_id' => $category->id,
        ], $manager['headers'])->assertCreated();

        $sessionId = $create->json('session.id');
        $this->assertIsString($sessionId);
        $create->assertJsonMissingPath('session.briskly_token');
        $this->assertStringNotContainsString('secret-briskly-token-value', $create->getContent());

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/snapshot', [], $manager['headers'])
            ->assertOk()
            ->assertJsonPath('snapshot_count', 2);

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/match', [], $manager['headers'])
            ->assertOk()
            ->assertJsonPath('session.status', 'matched');

        $results = $this->getJson(
            self::BASE.'/sessions/'.$sessionId.'/sync-results',
            $manager['headers'],
        )->assertOk();

        $this->assertStringNotContainsString('secret-briskly-token-value', $results->getContent());
        $results
            ->assertJsonPath('price_updates.total', 1)
            ->assertJsonPath('price_updates.items.0.source_price', '150.00')
            ->assertJsonPath('price_updates.items.0.briskly_price', '140.00')
            ->assertJsonPath('creates.total', 1)
            ->assertJsonPath('counts.skipped_briskly_only', 1);

        $show = $this->getJson(self::BASE.'/sessions/'.$sessionId, $manager['headers'])
            ->assertOk()
            ->assertJsonPath('session.clarification', 'игнорировать вес')
            ->assertJsonPath('session.vps_category_id', $category->id);
        $this->assertStringNotContainsString('secret-briskly-token-value', $show->getContent());
    }

    public function test_approvals_reject_client_price_and_apply_uses_server_price(): void
    {
        $manager = $this->maxManagerAuth(40_002);
        [$sessionId, $lineKey] = $this->seedMatchedPriceDiffSession($manager);

        $this->putJson(self::BASE.'/sessions/'.$sessionId.'/approvals', [
            'price_updates' => [[
                'line_key' => $lineKey,
                'apply' => true,
                'price' => '1.00',
            ]],
            'creates' => [],
        ], $manager['headers'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price_updates.0.price']);

        $this->fakeCategories = [
            new BrisklyCategoryDto(77, 'Супы', 5),
        ];

        $this->putJson(self::BASE.'/sessions/'.$sessionId.'/approvals', [
            'price_updates' => [[
                'line_key' => $lineKey,
                'apply' => true,
            ]],
            'creates' => [],
        ], $manager['headers'])
            ->assertOk()
            ->assertJsonPath('session.status', 'approved');

        $apply = $this->postJson(
            self::BASE.'/sessions/'.$sessionId.'/apply',
            [],
            $manager['headers'],
        )->assertOk();

        $apply->assertJsonPath('report.updated', 1);
        $this->assertCount(1, $this->brisklyWrites);
        $this->assertSame('update', $this->brisklyWrites[0]['op']);
        $this->assertSame('150.00', $this->brisklyWrites[0]['payload']['price']);

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/apply', [], $manager['headers'])
            ->assertStatus(409);
    }

    public function test_create_requires_category_and_categories_endpoint(): void
    {
        $manager = $this->maxManagerAuth(40_003);
        [$sessionId, $createKey] = $this->seedMatchedCreateSession($manager);

        $this->fakeCategories = [
            new BrisklyCategoryDto(88, 'Новые', 9),
        ];

        $this->getJson(
            self::BASE.'/briskly/categories?session_id='.$sessionId,
            $manager['headers'],
        )
            ->assertOk()
            ->assertJsonPath('categories.0.id', 88);

        $this->putJson(self::BASE.'/sessions/'.$sessionId.'/approvals', [
            'price_updates' => [],
            'creates' => [[
                'line_key' => $createKey,
                'apply' => true,
            ]],
        ], $manager['headers'])->assertStatus(422);

        $results = $this->getJson(
            self::BASE.'/sessions/'.$sessionId.'/sync-results',
            $manager['headers'],
        )->assertOk();
        $this->assertSame('Новое блюдо', $results->json('creates.items.0.display_name'));
        $this->assertSame('Новое блюдо, 200г', $results->json('creates.items.0.briskly_create_name'));

        $this->putJson(self::BASE.'/sessions/'.$sessionId.'/approvals', [
            'price_updates' => [],
            'creates' => [[
                'line_key' => $createKey,
                'apply' => true,
                'briskly_category_id' => 88,
            ]],
        ], $manager['headers'])->assertOk();

        $apply = $this->postJson(self::BASE.'/sessions/'.$sessionId.'/apply', [], $manager['headers'])
            ->assertOk()
            ->assertJsonPath('report.created', 1);

        $apply
            ->assertJsonPath('report.created_items.0.line_key', $createKey)
            ->assertJsonPath('report.created_items.0.briskly_item_id', 900002)
            ->assertJsonPath('report.created_items.0.barcode', '2000000000001')
            ->assertJsonPath('report.created_items.0.name', 'Новое блюдо, 200г');

        $this->assertSame('create', $this->brisklyWrites[0]['op']);
        $this->assertSame('Новое блюдо, 200г', $this->brisklyWrites[0]['payload']['name']);
        $this->assertSame(88, $this->brisklyWrites[0]['payload']['category_id']);
    }

    public function test_apply_rejects_briskly_item_outside_snapshot(): void
    {
        $manager = $this->maxManagerAuth(40_004);
        [$sessionId, $lineKey] = $this->seedMatchedPriceDiffSession($manager);

        // Подмена proposals: briskly_item_id вне snapshot.
        $session = BrisklySyncSession::query()->findOrFail($sessionId);
        $proposals = $session->proposals;
        $proposals['sync_results']['price_updates']['items'][0]['briskly_item_id'] = 777777;
        $session->proposals = $proposals;
        $session->save();

        $this->putJson(self::BASE.'/sessions/'.$sessionId.'/approvals', [
            'price_updates' => [['line_key' => $lineKey, 'apply' => true]],
            'creates' => [],
        ], $manager['headers'])->assertOk();

        $response = $this->postJson(self::BASE.'/sessions/'.$sessionId.'/apply', [], $manager['headers'])
            ->assertStatus(422);
        $this->assertStringContainsString('вне snapshot', (string) $response->json('message'));
    }

    public function test_expired_token_returns_422(): void
    {
        $manager = $this->maxManagerAuth(40_005);
        $restaurant = Restaurant::factory()->create(['is_active' => true]);

        $create = $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
        ], $manager['headers'])->assertCreated();

        $sessionId = $create->json('session.id');
        $this->app->make(BrisklySyncTokenStoreInterface::class)
            ->forget($sessionId);

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/snapshot', [], $manager['headers'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Токен Briskly недоступен — создайте сессию заново.');
    }

    public function test_create_session_stores_captured_token_without_client_token(): void
    {
        $manager = $this->maxManagerAuth(40_007);
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $this->fakeCaptureToken = 'captured-from-cdp-token-xyz';

        $create = $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
            'briskly_token' => 'client-must-be-ignored-token',
        ], $manager['headers'])->assertCreated();

        $sessionId = $create->json('session.id');
        $this->assertIsString($sessionId);
        $create->assertJsonMissingPath('session.briskly_token');
        $this->assertStringNotContainsString('captured-from-cdp-token-xyz', $create->getContent());
        $this->assertStringNotContainsString('client-must-be-ignored-token', $create->getContent());

        $stored = $this->app->make(BrisklySyncTokenStoreInterface::class)->get($sessionId);
        $this->assertSame('captured-from-cdp-token-xyz', $stored);
    }

    public function test_create_session_fails_when_capture_unavailable(): void
    {
        $manager = $this->maxManagerAuth(40_008);
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $this->captureFailure = new FoodDomainException(
            'Не удалось получить токен Briskly: Chrome CDP недоступен.',
            503,
        );

        $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
        ], $manager['headers'])
            ->assertStatus(503)
            ->assertJsonPath(
                'message',
                'Не удалось получить токен Briskly: Chrome CDP недоступен.',
            );

        $this->assertSame(0, BrisklySyncSession::query()->count());
    }

    public function test_orchestrator_unavailable_returns_503(): void
    {
        $manager = $this->maxManagerAuth(40_006);
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => false,
        ]);
        Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Суп',
            'price' => 100,
            'is_available' => true,
        ]);

        $this->fakeSnapshot = [
            new BrisklySnapshotItemDto(1, 'Суп', '90.00'),
        ];
        $this->orchestratorDown = true;

        $sessionId = $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
        ], $manager['headers'])->json('session.id');

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/snapshot', [], $manager['headers'])
            ->assertOk();

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/match', [], $manager['headers'])
            ->assertStatus(503);
    }

    /**
     * @param  array{user: MaxUser, headers: array<string, string>}  $manager
     * @return array{0: string, 1: string}
     */
    private function seedMatchedPriceDiffSession(array $manager): array
    {
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
        $lineKey = 'single:'.$dish->id;

        $this->fakeSnapshot = [
            new BrisklySnapshotItemDto(501, 'Борщ', '140.00'),
        ];
        $this->fakeMatchLines = [
            new MatchLineResultDto($lineKey, 'Борщ', 'борщ', [new MatchCandidateDto(501, 'Борщ')]),
        ];

        $sessionId = $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
        ], $manager['headers'])->json('session.id');

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/snapshot', [], $manager['headers']);
        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/match', [], $manager['headers']);

        return [$sessionId, $lineKey];
    }

    /**
     * @param  array{user: MaxUser, headers: array<string, string>}  $manager
     * @return array{0: string, 1: string}
     */
    private function seedMatchedCreateSession(array $manager): array
    {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => false,
        ]);
        $dish = Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Новое блюдо',
            'price' => 55,
            'weight' => 200,
            'weight_unit' => DishWeightUnit::Gram,
            'is_available' => true,
        ]);
        $lineKey = 'single:'.$dish->id;

        $this->fakeSnapshot = [
            new BrisklySnapshotItemDto(1, 'Другое', '10.00'),
        ];
        $this->fakeMatchLines = [
            new MatchLineResultDto($lineKey, 'Новое блюдо', 'новое блюдо', []),
        ];

        $sessionId = $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
        ], $manager['headers'])->json('session.id');

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/snapshot', [], $manager['headers']);
        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/match', [], $manager['headers']);

        return [$sessionId, $lineKey];
    }

    private function bindFakes(): void
    {
        $test = $this;

        $this->app->instance(BrisklySyncTokenCaptureGatewayInterface::class, new class($test) implements BrisklySyncTokenCaptureGatewayInterface
        {
            public function __construct(private AdminBrisklySyncSessionApiTest $test) {}

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
            public function __construct(private AdminBrisklySyncSessionApiTest $test) {}

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

        $this->app->instance(BrisklySyncMatchOrchestratorInterface::class, new class($test) implements BrisklySyncMatchOrchestratorInterface
        {
            public function __construct(private AdminBrisklySyncSessionApiTest $test) {}

            public function match(
                ComboCatalogPromptDto $prompt,
                array $sourceLines,
                array $brisklySnapshot,
                ?BrisklySyncLlmCallContextDto $logContext = null,
            ): array {
                if ($this->test->orchestratorDown) {
                    throw new FoodDomainException('Orchestrator Briskly sync недоступен.', 503);
                }

                return $this->test->fakeMatchLines;
            }
        });
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
