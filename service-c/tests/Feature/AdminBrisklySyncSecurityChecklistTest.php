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
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Food\BrisklySyncSession;
use App\Models\Food\Dish;
use App\Models\Food\MenuCategory;
use App\Models\Food\Restaurant;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Часть 4b — чеклист безопасности PHP Briskly sync до UI Apply.
 *
 * Закрывает: authz, UUID/IDOR v1, утечки токена, anti-tamper цен,
 * create category, идемпотентность apply, expired token, orchestrator 503, Δ>50%.
 */
class AdminBrisklySyncSecurityChecklistTest extends TestCase
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

    public string $fakeCaptureToken = 'test-captured-briskly-token';

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
        $this->fakeCaptureToken = 'test-captured-briskly-token';
        $this->captureFailure = null;
        $this->bindFakes();
    }

    public function test_authz_401_without_auth_and_403_without_max_manager(): void
    {
        $this->getJson(self::BASE.'/sessions/'.Str::uuid()->toString())
            ->assertUnauthorized();

        $auth = $this->authenticateMaxUser();
        $this->getJson(
            self::BASE.'/sessions/'.Str::uuid()->toString(),
            $auth['headers'],
        )->assertForbidden();

        $menu = $this->asFoodOrderAdmin($auth, FoodOrderAdminRole::MenuManager);
        $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => 1,
        ], $menu['headers'])->assertForbidden();
    }

    public function test_session_id_is_uuid_unknown_session_404(): void
    {
        $manager = $this->maxManagerAuth(50_001);
        $restaurant = Restaurant::factory()->create(['is_active' => true]);

        $create = $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
        ], $manager['headers'])->assertCreated();

        $sessionId = $create->json('session.id');
        $this->assertTrue(Str::isUuid($sessionId));

        // Последовательные int-id не принимаются маршрутом (whereUuid).
        $this->getJson(self::BASE.'/sessions/1', $manager['headers'])
            ->assertNotFound();

        $this->getJson(
            self::BASE.'/sessions/'.Str::uuid()->toString(),
            $manager['headers'],
        )->assertNotFound();
    }

    /**
     * IDOR v1: привязка к created_by отложена; достаточно роли max_manager.
     * Другой max_manager может читать сессию по UUID — N/A для v1 (зафиксировано).
     */
    public function test_idor_v1_any_max_manager_may_read_session_by_uuid(): void
    {
        $owner = $this->maxManagerAuth(50_002);
        $other = $this->maxManagerAuth(50_003);
        $restaurant = Restaurant::factory()->create(['is_active' => true]);

        $sessionId = $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
        ], $owner['headers'])->json('session.id');

        $this->getJson(self::BASE.'/sessions/'.$sessionId, $other['headers'])
            ->assertOk()
            ->assertJsonPath('session.id', $sessionId);
    }

    public function test_token_never_leaks_in_get_responses_and_forgotten_after_apply(): void
    {
        $manager = $this->maxManagerAuth(50_004);
        $secret = 'secret-briskly-token-checklist-4b';
        $this->fakeCaptureToken = $secret;
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => false,
        ]);
        $dish = Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Суп',
            'price' => 150,
            'is_available' => true,
        ]);
        $lineKey = 'single:'.$dish->id;
        $this->fakeSnapshot = [new BrisklySnapshotItemDto(501, 'Суп', '140.00')];
        $this->fakeMatchLines = [
            new MatchLineResultDto($lineKey, 'Суп', 'суп', [new MatchCandidateDto(501, 'Суп')]),
        ];

        $sessionId = $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
        ], $manager['headers'])->json('session.id');

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/snapshot', [], $manager['headers'])->assertOk();
        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/match', [], $manager['headers'])->assertOk();

        foreach ([
            $this->getJson(self::BASE.'/sessions/'.$sessionId, $manager['headers']),
            $this->getJson(self::BASE.'/sessions/'.$sessionId.'/sync-results', $manager['headers']),
        ] as $response) {
            $response->assertOk();
            $this->assertStringNotContainsString($secret, $response->getContent());
            $response->assertJsonMissingPath('briskly_token');
            $response->assertJsonMissingPath('session.briskly_token');
        }

        $this->putJson(self::BASE.'/sessions/'.$sessionId.'/approvals', [
            'price_updates' => [['line_key' => $lineKey, 'apply' => true]],
            'creates' => [],
        ], $manager['headers'])->assertOk();

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/apply', [], $manager['headers'])
            ->assertOk();

        $this->assertNull(
            $this->app->make(BrisklySyncTokenStoreInterface::class)->get($sessionId),
        );

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/snapshot', [], $manager['headers'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Токен Briskly недоступен — создайте сессию заново.');
    }

    public function test_client_price_prohibited_and_apply_uses_server_source_price(): void
    {
        $manager = $this->maxManagerAuth(50_005);
        [$sessionId, $lineKey] = $this->seedMatchedPriceDiffSession($manager, sourcePrice: 150, brisklyPrice: 140);

        $this->putJson(self::BASE.'/sessions/'.$sessionId.'/approvals', [
            'price_updates' => [[
                'line_key' => $lineKey,
                'apply' => true,
                'price' => '0.01',
            ]],
            'creates' => [],
        ], $manager['headers'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price_updates.0.price']);

        $this->putJson(self::BASE.'/sessions/'.$sessionId.'/approvals', [
            'price_updates' => [['line_key' => $lineKey, 'apply' => true]],
            'creates' => [],
        ], $manager['headers'])->assertOk();

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/apply', [], $manager['headers'])
            ->assertOk()
            ->assertJsonPath('report.updated', 1);

        $this->assertSame('150.00', $this->brisklyWrites[0]['payload']['price']);
    }

    public function test_large_delta_requires_confirm_before_any_briskly_write(): void
    {
        $manager = $this->maxManagerAuth(50_006);
        // 200 vs 100 → Δ = 100% > 50%.
        [$sessionId, $lineKey] = $this->seedMatchedPriceDiffSession($manager, sourcePrice: 200, brisklyPrice: 100);

        $this->putJson(self::BASE.'/sessions/'.$sessionId.'/approvals', [
            'price_updates' => [['line_key' => $lineKey, 'apply' => true]],
            'creates' => [],
        ], $manager['headers'])->assertOk();

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/apply', [], $manager['headers'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Требуется confirm_large_delta для Δ цены > 50%.');

        $this->assertSame([], $this->brisklyWrites);
        $this->assertSame(
            'approved',
            BrisklySyncSession::query()->findOrFail($sessionId)->status,
        );

        $this->putJson(self::BASE.'/sessions/'.$sessionId.'/approvals', [
            'price_updates' => [[
                'line_key' => $lineKey,
                'apply' => true,
                'confirm_large_delta' => true,
            ]],
            'creates' => [],
        ], $manager['headers'])->assertOk();

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/apply', [], $manager['headers'])
            ->assertOk()
            ->assertJsonPath('report.updated', 1);

        $this->assertSame('200.00', $this->brisklyWrites[0]['payload']['price']);
    }

    public function test_source_price_changed_after_match_requires_rematch(): void
    {
        $manager = $this->maxManagerAuth(50_007);
        [$sessionId, $lineKey, $dish] = $this->seedMatchedPriceDiffSessionWithDish(
            $manager,
            sourcePrice: 150,
            brisklyPrice: 140,
        );

        $this->putJson(self::BASE.'/sessions/'.$sessionId.'/approvals', [
            'price_updates' => [['line_key' => $lineKey, 'apply' => true]],
            'creates' => [],
        ], $manager['headers'])->assertOk();

        $dish->price = 999;
        $dish->save();

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/apply', [], $manager['headers'])
            ->assertStatus(409)
            ->assertJsonPath(
                'message',
                'Цены source изменились с момента match — выполните rematch.',
            );
        $this->assertSame([], $this->brisklyWrites);
    }

    public function test_create_rejects_category_outside_session_list(): void
    {
        $manager = $this->maxManagerAuth(50_008);
        [$sessionId, $createKey] = $this->seedMatchedCreateSession($manager);

        $this->fakeCategories = [
            new BrisklyCategoryDto(88, 'Разрешённая', 9),
        ];

        $this->getJson(
            self::BASE.'/briskly/categories?session_id='.$sessionId,
            $manager['headers'],
        )->assertOk();

        $this->putJson(self::BASE.'/sessions/'.$sessionId.'/approvals', [
            'price_updates' => [],
            'creates' => [[
                'line_key' => $createKey,
                'apply' => true,
                'briskly_category_id' => 999999,
            ]],
        ], $manager['headers'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'briskly_category_id не из списка категорий сессии.');
    }

    public function test_apply_rejects_briskly_item_outside_snapshot_and_unknown_line_key(): void
    {
        $manager = $this->maxManagerAuth(50_009);
        [$sessionId, $lineKey] = $this->seedMatchedPriceDiffSession($manager, sourcePrice: 150, brisklyPrice: 140);

        $this->putJson(self::BASE.'/sessions/'.$sessionId.'/approvals', [
            'price_updates' => [['line_key' => 'missing:line', 'apply' => true]],
            'creates' => [],
        ], $manager['headers'])
            ->assertStatus(422);

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
        $this->assertSame([], $this->brisklyWrites);
    }

    public function test_repeat_apply_returns_409_and_restaurant_deactivated_blocks_apply(): void
    {
        $manager = $this->maxManagerAuth(50_010);
        [$sessionId, $lineKey, $dish] = $this->seedMatchedPriceDiffSessionWithDish(
            $manager,
            sourcePrice: 150,
            brisklyPrice: 140,
        );

        $restaurantId = (int) MenuCategory::query()
            ->whereKey($dish->menu_category_id)
            ->value('restaurant_id');
        $restaurant = Restaurant::query()->findOrFail($restaurantId);

        $this->putJson(self::BASE.'/sessions/'.$sessionId.'/approvals', [
            'price_updates' => [['line_key' => $lineKey, 'apply' => true]],
            'creates' => [],
        ], $manager['headers'])->assertOk();

        $restaurant->is_active = false;
        $restaurant->save();

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/apply', [], $manager['headers'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ресторан не найден или неактивен.');
        $this->assertSame([], $this->brisklyWrites);

        $restaurant->is_active = true;
        $restaurant->save();

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/apply', [], $manager['headers'])
            ->assertOk();

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/apply', [], $manager['headers'])
            ->assertStatus(409);
    }

    public function test_expired_token_and_orchestrator_503(): void
    {
        $manager = $this->maxManagerAuth(50_011);
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

        $sessionId = $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
        ], $manager['headers'])->json('session.id');

        $this->app->make(BrisklySyncTokenStoreInterface::class)->forget($sessionId);

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/snapshot', [], $manager['headers'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Токен Briskly недоступен — создайте сессию заново.');

        $sessionId2 = $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
        ], $manager['headers'])->json('session.id');

        $this->fakeSnapshot = [new BrisklySnapshotItemDto(1, 'Суп', '90.00')];
        $this->orchestratorDown = true;

        $this->postJson(self::BASE.'/sessions/'.$sessionId2.'/snapshot', [], $manager['headers'])
            ->assertOk();

        $this->postJson(self::BASE.'/sessions/'.$sessionId2.'/match', [], $manager['headers'])
            ->assertStatus(503);

        $this->assertSame(
            'failed',
            BrisklySyncSession::query()->findOrFail($sessionId2)->status,
        );
    }

    /**
     * @param  array{user: MaxUser, headers: array<string, string>}  $manager
     * @return array{0: string, 1: string}
     */
    private function seedMatchedPriceDiffSession(
        array $manager,
        float $sourcePrice,
        float $brisklyPrice,
    ): array {
        [$sessionId, $lineKey] = $this->seedMatchedPriceDiffSessionWithDish(
            $manager,
            $sourcePrice,
            $brisklyPrice,
        );

        return [$sessionId, $lineKey];
    }

    /**
     * @param  array{user: MaxUser, headers: array<string, string>}  $manager
     * @return array{0: string, 1: string, 2: Dish}
     */
    private function seedMatchedPriceDiffSessionWithDish(
        array $manager,
        float $sourcePrice,
        float $brisklyPrice,
    ): array {
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => false,
        ]);
        $dish = Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Борщ',
            'price' => $sourcePrice,
            'is_available' => true,
        ]);
        $lineKey = 'single:'.$dish->id;

        $this->fakeSnapshot = [
            new BrisklySnapshotItemDto(501, 'Борщ', number_format($brisklyPrice, 2, '.', '')),
        ];
        $this->fakeMatchLines = [
            new MatchLineResultDto($lineKey, 'Борщ', 'борщ', [new MatchCandidateDto(501, 'Борщ')]),
        ];

        $sessionId = $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
        ], $manager['headers'])->json('session.id');

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/snapshot', [], $manager['headers']);
        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/match', [], $manager['headers']);

        return [$sessionId, $lineKey, $dish];
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
            public function __construct(private AdminBrisklySyncSecurityChecklistTest $test) {}

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
            public function __construct(private AdminBrisklySyncSecurityChecklistTest $test) {}

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
            public function __construct(private AdminBrisklySyncSecurityChecklistTest $test) {}

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
                'first_name' => 'SecMgr'.$maxUserId,
            ])),
            FoodOrderAdminRole::MaxManager,
        );
    }
}
