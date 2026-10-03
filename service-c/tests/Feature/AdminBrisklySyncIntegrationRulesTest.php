<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Food\BrisklySync\BrisklyCatalogGatewayInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncMatchOrchestratorInterface;
use App\DTO\Food\BrisklySync\BrisklyCategoryDto;
use App\DTO\Food\BrisklySync\BrisklyCreatedItemDto;
use App\DTO\Food\BrisklySync\BrisklySnapshotItemDto;
use App\DTO\Food\BrisklySync\MatchCandidateDto;
use App\DTO\Food\BrisklySync\MatchLineResultDto;
use App\DTO\Food\ComboCatalog\ComboCatalogPromptDto;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Food\Dish;
use App\Models\Food\MenuCategory;
use App\Models\Food\Restaurant;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Часть 7 — feature/integration: правила 1D / 2B / 3B + фильтры сессии + security smoke.
 *
 * БД: sail_db_testing (phpunit.xml / TestCase).
 */
class AdminBrisklySyncIntegrationRulesTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetFoodDomainTables();
        $this->fakeSnapshot = [];
        $this->fakeCategories = [];
        $this->fakeMatchLines = [];
        $this->brisklyWrites = [];
        $this->bindFakes();
    }

    /**
     * 1D: briskly-only не в sync-results; VPS-only → creates.
     * 2B: лимит 25 на секцию + truncated.
     * 3B: apply:false по умолчанию — сессия не approved; отмеченные — apply, остальные skipped_unchecked.
     */
    public function test_1d_2b_3b_classification_cap_and_explicit_opt_in_apply(): void
    {
        $manager = $this->maxManagerAuth(70_001);
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $category = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => false,
        ]);

        $priceDiffKeys = [];
        $createKeys = [];
        $equalKey = null;
        $ambiguousKey = null;

        // 26 price-diff + 1 equal + 1 ambiguous + 26 VPS-only creates.
        for ($i = 1; $i <= 26; $i++) {
            $dish = Dish::factory()->create([
                'menu_category_id' => $category->id,
                'name' => 'Diff '.$i,
                'price' => 100 + $i,
                'is_available' => true,
            ]);
            $key = 'single:'.$dish->id;
            $priceDiffKeys[] = $key;
            $this->fakeSnapshot[] = new BrisklySnapshotItemDto(
                1000 + $i,
                'Diff '.$i,
                number_format(90 + $i, 2, '.', ''),
            );
            $this->fakeMatchLines[] = new MatchLineResultDto(
                $key,
                'Diff '.$i,
                'diff '.$i,
                [new MatchCandidateDto(1000 + $i, 'Diff '.$i)],
            );
        }

        $equalDish = Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Equal Same',
            'price' => 50,
            'is_available' => true,
        ]);
        $equalKey = 'single:'.$equalDish->id;
        $this->fakeSnapshot[] = new BrisklySnapshotItemDto(2000, 'Equal Same', '50.00');
        $this->fakeMatchLines[] = new MatchLineResultDto(
            $equalKey,
            'Equal Same',
            'equal same',
            [new MatchCandidateDto(2000, 'Equal Same')],
        );

        $ambiguousDish = Dish::factory()->create([
            'menu_category_id' => $category->id,
            'name' => 'Ambiguous',
            'price' => 70,
            'is_available' => true,
        ]);
        $ambiguousKey = 'single:'.$ambiguousDish->id;
        $this->fakeSnapshot[] = new BrisklySnapshotItemDto(3001, 'Ambiguous A', '70.00');
        $this->fakeSnapshot[] = new BrisklySnapshotItemDto(3002, 'Ambiguous B', '71.00');
        $this->fakeMatchLines[] = new MatchLineResultDto(
            $ambiguousKey,
            'Ambiguous',
            'ambiguous',
            [
                new MatchCandidateDto(3001, 'Ambiguous A'),
                new MatchCandidateDto(3002, 'Ambiguous B'),
            ],
        );

        for ($i = 1; $i <= 26; $i++) {
            $dish = Dish::factory()->create([
                'menu_category_id' => $category->id,
                'name' => 'Create '.$i,
                'price' => 30 + $i,
                'is_available' => true,
            ]);
            $key = 'single:'.$dish->id;
            $createKeys[] = $key;
            $this->fakeMatchLines[] = new MatchLineResultDto(
                $key,
                'Create '.$i,
                'create '.$i,
                [],
            );
        }

        // 1D: только в Briskly — в результаты не попадает.
        $this->fakeSnapshot[] = new BrisklySnapshotItemDto(9999, 'Only Briskly', '1.00');

        $sessionId = $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
            'briskly_token' => str_repeat('i', 24),
            'vps_category_id' => $category->id,
            'clarification' => 'игнорировать скобки и вес',
        ], $manager['headers'])
            ->assertCreated()
            ->assertJsonPath('session.clarification', 'игнорировать скобки и вес')
            ->assertJsonPath('session.vps_category_id', $category->id)
            ->json('session.id');

        $this->assertStringNotContainsString(str_repeat('i', 24), (string) json_encode(
            $this->getJson(self::BASE.'/sessions/'.$sessionId, $manager['headers'])->json(),
        ));

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/snapshot', [], $manager['headers'])
            ->assertOk();
        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/match', [], $manager['headers'])
            ->assertOk()
            ->assertJsonPath('session.status', 'matched');

        $results = $this->getJson(
            self::BASE.'/sessions/'.$sessionId.'/sync-results',
            $manager['headers'],
        )->assertOk();

        // 2B: cap 25 + truncated на обеих секциях.
        $results
            ->assertJsonPath('price_updates.total', 26)
            ->assertJsonPath('price_updates.shown', 25)
            ->assertJsonPath('price_updates.truncated', true)
            ->assertJsonPath('creates.total', 26)
            ->assertJsonPath('creates.shown', 25)
            ->assertJsonPath('creates.truncated', true)
            ->assertJsonPath('counts.equal_price', 1)
            ->assertJsonPath('counts.ambiguous', 1);

        $this->assertGreaterThanOrEqual(1, (int) $results->json('counts.skipped_briskly_only'));

        $priceItems = $results->json('price_updates.items');
        $createItems = $results->json('creates.items');
        $this->assertIsArray($priceItems);
        $this->assertIsArray($createItems);
        $this->assertCount(25, $priceItems);
        $this->assertCount(25, $createItems);

        // 1D: briskly-only и equal/ambiguous не в таблицах.
        $shownPriceKeys = array_column($priceItems, 'line_key');
        $shownCreateKeys = array_column($createItems, 'line_key');
        $shownBrisklyIds = array_column($priceItems, 'briskly_item_id');

        $this->assertNotContains($equalKey, $shownPriceKeys);
        $this->assertNotContains($ambiguousKey, $shownPriceKeys);
        $this->assertNotContains(9999, $shownBrisklyIds);
        $this->assertNotContains($equalKey, $shownCreateKeys);
        $this->assertNotContains($ambiguousKey, $shownCreateKeys);

        foreach ($shownCreateKeys as $key) {
            $this->assertContains($key, $createKeys);
        }

        // 3B: все apply:false → статус остаётся matched, apply запрещён.
        $allUncheckedUpdates = array_map(
            static fn (array $item): array => [
                'line_key' => $item['line_key'],
                'apply' => false,
            ],
            $priceItems,
        );
        $allUncheckedCreates = array_map(
            static fn (array $item): array => [
                'line_key' => $item['line_key'],
                'apply' => false,
            ],
            $createItems,
        );

        $this->putJson(self::BASE.'/sessions/'.$sessionId.'/approvals', [
            'price_updates' => $allUncheckedUpdates,
            'creates' => $allUncheckedCreates,
        ], $manager['headers'])
            ->assertOk()
            ->assertJsonPath('session.status', 'matched');

        $this->postJson(self::BASE.'/sessions/'.$sessionId.'/apply', [], $manager['headers'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Сначала сохраните approvals со статусом approved.');
        $this->assertSame([], $this->brisklyWrites);

        // 3B: отмечаем одну UPDATE и одну CREATE — остальные unchecked.
        $this->fakeCategories = [new BrisklyCategoryDto(55, 'Супы', 1)];
        $this->getJson(
            self::BASE.'/briskly/categories?session_id='.$sessionId,
            $manager['headers'],
        )->assertOk()->assertJsonPath('categories.0.id', 55);

        $firstUpdateKey = $priceItems[0]['line_key'];
        $firstCreateKey = $createItems[0]['line_key'];
        $firstSourcePrice = $priceItems[0]['source_price'];

        $mixedUpdates = $allUncheckedUpdates;
        $mixedUpdates[0]['apply'] = true;
        $mixedCreates = $allUncheckedCreates;
        $mixedCreates[0] = [
            'line_key' => $firstCreateKey,
            'apply' => true,
            'briskly_category_id' => 55,
        ];

        $this->putJson(self::BASE.'/sessions/'.$sessionId.'/approvals', [
            'price_updates' => $mixedUpdates,
            'creates' => $mixedCreates,
        ], $manager['headers'])
            ->assertOk()
            ->assertJsonPath('session.status', 'approved');

        $apply = $this->postJson(self::BASE.'/sessions/'.$sessionId.'/apply', [], $manager['headers'])
            ->assertOk();

        $apply
            ->assertJsonPath('report.updated', 1)
            ->assertJsonPath('report.created', 1)
            ->assertJsonPath('report.skipped_unchecked', 48)
            ->assertJsonPath('report.created_items.0.line_key', $firstCreateKey)
            ->assertJsonPath('report.created_items.0.briskly_item_id', 900003)
            ->assertJsonPath('report.created_items.0.barcode', '2000000000001');

        $this->assertCount(2, $this->brisklyWrites);
        $this->assertSame('update', $this->brisklyWrites[0]['op']);
        $this->assertSame($firstSourcePrice, $this->brisklyWrites[0]['payload']['price']);
        $this->assertSame('create', $this->brisklyWrites[1]['op']);
        $this->assertSame(55, $this->brisklyWrites[1]['payload']['category_id']);
        $this->assertSame($firstUpdateKey, $mixedUpdates[0]['line_key']);
        $this->assertNotEmpty($apply->json('report.created_items.0.name'));
    }

    public function test_session_filters_and_clarification_validation(): void
    {
        $manager = $this->maxManagerAuth(70_002);
        $restaurant = Restaurant::factory()->create(['is_active' => true]);
        $ownCategory = MenuCategory::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_combo_available' => false,
        ]);
        $foreignRestaurant = Restaurant::factory()->create(['is_active' => true]);
        $foreignCategory = MenuCategory::factory()->create([
            'restaurant_id' => $foreignRestaurant->id,
            'is_combo_available' => false,
        ]);

        $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
            'briskly_token' => str_repeat('v', 24),
            'vps_category_id' => $foreignCategory->id,
        ], $manager['headers'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vps_category_id']);

        $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
            'briskly_token' => str_repeat('v', 24),
            'search_text' => str_repeat('x', 121),
        ], $manager['headers'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['search_text']);

        $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
            'briskly_token' => str_repeat('v', 24),
            'clarification' => str_repeat('y', 2001),
        ], $manager['headers'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['clarification']);

        $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
            'briskly_token' => str_repeat('v', 24),
            'vps_category_id' => $ownCategory->id,
            'search_text' => 'борщ',
            'clarification' => 'не учитывать вес',
        ], $manager['headers'])
            ->assertCreated()
            ->assertJsonPath('session.vps_category_id', $ownCategory->id)
            ->assertJsonPath('session.search_text', 'борщ')
            ->assertJsonPath('session.clarification', 'не учитывать вес')
            ->assertJsonMissingPath('session.briskly_token');
    }

    public function test_security_smoke_menu_manager_forbidden_and_get_without_token(): void
    {
        $auth = $this->authenticateMaxUser(MaxUser::query()->create([
            'max_user_id' => 70_003,
            'first_name' => 'MenuOnly',
        ]));
        $menu = $this->asFoodOrderAdmin($auth, FoodOrderAdminRole::MenuManager);
        $restaurant = Restaurant::factory()->create(['is_active' => true]);

        $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
            'briskly_token' => str_repeat('m', 24),
        ], $menu['headers'])->assertForbidden();

        $manager = $this->maxManagerAuth(70_004);
        $secret = 'token-must-not-leak-in-get-'.$manager['user']->max_user_id;
        $sessionId = $this->postJson(self::BASE.'/sessions', [
            'restaurant_id' => $restaurant->id,
            'briskly_token' => $secret,
        ], $manager['headers'])->json('session.id');

        $show = $this->getJson(self::BASE.'/sessions/'.$sessionId, $manager['headers'])
            ->assertOk();
        $this->assertStringNotContainsString($secret, $show->getContent());
        $show->assertJsonMissingPath('session.briskly_token');
    }

    private function bindFakes(): void
    {
        $test = $this;

        $this->app->instance(BrisklyCatalogGatewayInterface::class, new class($test) implements BrisklyCatalogGatewayInterface
        {
            public function __construct(private AdminBrisklySyncIntegrationRulesTest $test) {}

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
            public function __construct(private AdminBrisklySyncIntegrationRulesTest $test) {}

            public function match(
                ComboCatalogPromptDto $prompt,
                array $sourceLines,
                array $brisklySnapshot,
            ): array {
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
                'first_name' => 'IntMgr'.$maxUserId,
            ])),
            FoodOrderAdminRole::MaxManager,
        );
    }
}
