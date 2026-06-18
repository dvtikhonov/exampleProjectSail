<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrganizationSyncStatus;
use App\Jobs\SyncYandexOrganizationReviewsJob;
use App\Models\Organization;
use App\Models\OrganizationReview;
use App\Models\User;
use App\Services\YandexMaps\OrganizationReviewQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\FakesYandexParser;
use Tests\Support\MakesStatefulApiRequests;
use Tests\Support\YandexParserFixtures;
use Tests\TestCase;

class OrganizationApiTest extends TestCase
{
    use FakesYandexParser;
    use MakesStatefulApiRequests;
    use RefreshDatabase;

    private const YANDEX_URL = 'https://yandex.ru/maps/org/test-cafe/1248139252/';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpYandexParserConfig();
    }

    public function test_guest_cannot_access_organization_endpoints(): void
    {
        $this->getStatefulJson('/api/organization')->assertUnauthorized();
        $this->postStatefulJson('/api/organization/resolve', ['url' => self::YANDEX_URL])->assertUnauthorized();
        $this->postStatefulJson('/api/organization/confirm', [
            'session_id' => (string) Str::uuid(),
            'org_id' => '1248139252',
        ])->assertUnauthorized();
        $this->getStatefulJson('/api/organization/sync-status')->assertUnauthorized();
        $this->postStatefulJson('/api/organization/resync')->assertUnauthorized();
        $this->getStatefulJson('/api/organization/reviews')->assertUnauthorized();
    }

    public function test_show_returns_null_when_organization_not_configured(): void
    {
        $user = User::factory()->create();

        $this->actingAsStateful($user)
            ->getStatefulJson('/api/organization')
            ->assertOk()
            ->assertJson([
                'organization' => null,
            ]);
    }

    public function test_sync_status_returns_422_when_organization_id_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAsStateful($user)
            ->getStatefulJson('/api/organization/sync-status')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_id']);
    }

    public function test_reviews_returns_422_when_organization_id_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAsStateful($user)
            ->getStatefulJson('/api/organization/reviews')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_id']);
    }

    public function test_resolve_rejects_invalid_url(): void
    {
        $user = User::factory()->create();

        $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/resolve', [
                'url' => 'Новокузнецк www.invitro.ru',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url']);
    }

    public function test_resolve_accepts_website_with_clarification(): void
    {
        $user = User::factory()->create();
        $fixture = YandexParserFixtures::loadCollect('multiple_candidates');

        $this->fakeYandexParserCollect($fixture);

        $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/resolve', [
                'url' => 'www.invitro.ru Новокузнецк',
            ])
            ->assertOk()
            ->assertJsonPath('input_url', 'www.invitro.ru Новокузнецк')
            ->assertJsonPath('search_text', 'www.invitro.ru Новокузнецк')
            ->assertJsonPath('clarification', 'Новокузнецк');
    }

    public function test_resolve_filters_candidates_by_city_clarification(): void
    {
        $user = User::factory()->create();
        $fixture = YandexParserFixtures::loadCollect('invitro_multi_city');

        $this->fakeYandexParserCollect($fixture);

        $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/resolve', [
                'url' => 'www.invitro.ru Новокузнецк',
            ])
            ->assertOk()
            ->assertJsonPath('search_text', 'www.invitro.ru Новокузнецк')
            ->assertJsonPath('clarification', 'Новокузнецк')
            ->assertJsonPath('match_count', 1)
            ->assertJsonCount(1, 'candidates')
            ->assertJsonPath('candidates.0.org_id', '1000000001')
            ->assertJsonPath('candidates.0.address', 'Новокузнецк, проспект Металлургов, 29');
    }

    public function test_resolve_filters_candidates_by_street_in_clarification(): void
    {
        $user = User::factory()->create();
        $fixture = YandexParserFixtures::loadCollect('invitro_same_city');

        $this->fakeYandexParserCollect($fixture);

        $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/resolve', [
                'url' => 'www.invitro.ru Новокузнецк проспект Металлургов 29',
            ])
            ->assertOk()
            ->assertJsonPath('clarification', 'Новокузнецк проспект Металлургов 29')
            ->assertJsonPath('search_text', 'www.invitro.ru Новокузнецк проспект Металлургов 29')
            ->assertJsonPath('match_count', 1)
            ->assertJsonPath('candidates.0.org_id', '1000000001')
            ->assertJsonPath('candidates.0.address', 'Новокузнецк, проспект Металлургов, 29');
    }

    public function test_resolve_returns_502_when_parser_fails(): void
    {
        $user = User::factory()->create();

        $this->fakeYandexParserCollect([
            'message' => 'SmartCaptcha',
        ], 503);

        $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/resolve', [
                'url' => self::YANDEX_URL,
            ])
            ->assertStatus(502)
            ->assertJson([
                'message' => 'SmartCaptcha',
            ]);
    }

    public function test_resolve_returns_candidates_with_session(): void
    {
        $user = User::factory()->create();
        $fixture = YandexParserFixtures::loadCollect('multiple_candidates');

        $this->fakeYandexParserCollect($fixture);

        $response = $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/resolve', [
                'url' => 'https://yandex.ru/maps/moscow/search/cafe',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'session_id',
                'input_url',
                'search_text',
                'clarification',
                'resolved_url',
                'match_count',
                'candidates' => [
                    ['org_id', 'name', 'address', 'average_rating', 'reviews_count', 'ratings_count', 'canonical_url'],
                ],
                'auto_selected',
            ])
            ->assertJson([
                'input_url' => 'https://yandex.ru/maps/moscow/search/cafe',
                'resolved_url' => $fixture['resolved_url'],
                'match_count' => 2,
                'auto_selected' => false,
            ]);

        $this->assertNotEmpty($response->json('session_id'));
    }

    public function test_resolve_sets_auto_selected_when_single_candidate(): void
    {
        $user = User::factory()->create();
        $fixture = YandexParserFixtures::loadCollect('single_candidate');

        $this->fakeYandexParserCollect($fixture);

        $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/resolve', [
                'url' => self::YANDEX_URL,
            ])
            ->assertOk()
            ->assertJson([
                'match_count' => 1,
                'auto_selected' => true,
                'candidates' => [
                    [
                        'org_id' => '1248139252',
                        'name' => 'Кафе X',
                    ],
                ],
            ]);
    }

    public function test_confirm_returns_422_for_expired_session(): void
    {
        $user = User::factory()->create();

        $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/confirm', [
                'session_id' => (string) Str::uuid(),
                'org_id' => '1248139252',
            ])
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Сессия поиска организации истекла. Повторите поиск по ссылке.',
            ]);
    }

    public function test_confirm_returns_422_for_invalid_org_id(): void
    {
        $user = User::factory()->create();
        $fixture = YandexParserFixtures::loadCollect('multiple_candidates');

        $this->fakeYandexParserCollect($fixture);

        $resolveResponse = $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/resolve', [
                'url' => 'https://yandex.ru/maps/moscow/search/cafe',
            ])
            ->assertOk();

        $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/confirm', [
                'session_id' => $resolveResponse->json('session_id'),
                'org_id' => '9999999999',
            ])
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Выбранная организация не найдена среди результатов поиска.',
            ]);
    }

    public function test_confirm_creates_organization_and_dispatches_sync_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $fixture = YandexParserFixtures::loadCollect('single_candidate');

        $this->fakeYandexParserCollect($fixture);

        $resolveResponse = $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/resolve', [
                'url' => self::YANDEX_URL,
            ])
            ->assertOk();

        $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/confirm', [
                'session_id' => $resolveResponse->json('session_id'),
                'org_id' => '1248139252',
            ])
            ->assertAccepted()
            ->assertJson([
                'organization' => [
                    'yandex_org_id' => '1248139252',
                    'name' => 'Кафе X',
                    'sync_status' => OrganizationSyncStatus::Pending->value,
                ],
            ]);

        $this->assertDatabaseHas('organizations', [
            'user_id' => $user->id,
            'yandex_org_id' => '1248139252',
            'name' => 'Кафе X',
            'sync_status' => OrganizationSyncStatus::Pending->value,
        ]);

        Queue::assertPushed(SyncYandexOrganizationReviewsJob::class);
    }

    public function test_confirm_triggers_sync_and_persists_reviews(): void
    {
        $user = User::factory()->create();
        $resolveFixture = YandexParserFixtures::loadCollect('single_candidate');
        $syncFixture = YandexParserFixtures::load('sync_reviews');

        $this->fakeYandexParser($resolveFixture, $syncFixture);

        $resolveResponse = $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/resolve', [
                'url' => self::YANDEX_URL,
            ])
            ->assertOk();

        $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/confirm', [
                'session_id' => $resolveResponse->json('session_id'),
                'org_id' => '1248139252',
            ])
            ->assertAccepted();

        $organization = Organization::query()
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($organization);
        $this->assertSame(OrganizationSyncStatus::Completed, $organization->sync_status);
        $this->assertSame(2, $organization->reviews()->count());
        $this->assertDatabaseHas('organization_reviews', [
            'organization_id' => $organization->id,
            'external_review_id' => 'rev-001',
            'author_name' => 'Иван Петров',
            'rating' => 5,
        ]);
    }

    public function test_show_returns_organization_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create([
            'sync_status' => OrganizationSyncStatus::Completed,
        ]);

        $this->actingAsStateful($user)
            ->getStatefulJson('/api/organization')
            ->assertOk()
            ->assertJson([
                'organization' => [
                    'id' => $organization->id,
                    'yandex_org_id' => $organization->yandex_org_id,
                    'name' => $organization->name,
                    'sync_status' => OrganizationSyncStatus::Completed->value,
                ],
            ]);
    }

    public function test_show_returns_requested_organization_when_user_has_multiple(): void
    {
        $user = User::factory()->create();
        Organization::factory()->for($user)->create([
            'yandex_org_id' => fake()->unique()->numerify('##########'),
            'name' => 'Вкусно — и точка',
            'sync_status' => OrganizationSyncStatus::Completed,
        ]);
        $invitro = Organization::factory()->for($user)->create([
            'yandex_org_id' => fake()->unique()->numerify('##########'),
            'name' => 'Invitro',
            'address' => 'ул. Тореза, 61, Новокузнецк',
            'sync_status' => OrganizationSyncStatus::Completed,
        ]);

        $this->actingAsStateful($user)
            ->getStatefulJson('/api/organization?organization_id='.$invitro->id)
            ->assertOk()
            ->assertJson([
                'organization' => [
                    'id' => $invitro->id,
                    'name' => 'Invitro',
                    'address' => 'ул. Тореза, 61, Новокузнецк',
                ],
            ]);
    }

    public function test_show_returns_null_for_organization_owned_by_another_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $organization = Organization::factory()->for($owner)->create();

        $this->actingAsStateful($otherUser)
            ->getStatefulJson('/api/organization?organization_id='.$organization->id)
            ->assertOk()
            ->assertJson([
                'organization' => null,
            ]);
    }

    public function test_sync_status_returns_current_status(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create([
            'sync_status' => OrganizationSyncStatus::Completed,
            'sync_error' => null,
        ]);

        $this->actingAsStateful($user)
            ->getStatefulJson('/api/organization/sync-status?organization_id='.$organization->id)
            ->assertOk()
            ->assertJson([
                'sync_status' => OrganizationSyncStatus::Completed->value,
                'sync_error' => null,
            ])
            ->assertJsonStructure(['last_synced_at']);
    }

    public function test_resync_sets_pending_and_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create([
            'sync_status' => OrganizationSyncStatus::Completed,
        ]);

        $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/resync', [
                'organization_id' => $organization->id,
            ])
            ->assertAccepted()
            ->assertJson([
                'organization' => [
                    'id' => $organization->id,
                    'sync_status' => OrganizationSyncStatus::Pending->value,
                ],
            ]);

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'sync_status' => OrganizationSyncStatus::Pending->value,
        ]);

        Queue::assertPushed(SyncYandexOrganizationReviewsJob::class);
    }

    public function test_resync_returns_422_when_organization_id_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAsStateful($user)
            ->postStatefulJson('/api/organization/resync')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_id']);
    }

    public function test_reviews_returns_paginated_reviews(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create([
            'name' => 'Кафе X',
            'average_rating' => 4.8,
            'ratings_count' => 1200,
            'reviews_count' => 3,
            'sync_status' => OrganizationSyncStatus::Completed,
        ]);

        OrganizationReview::factory()
            ->count(3)
            ->for($organization)
            ->sequence(
                ['sort_order' => 0, 'author_name' => 'Автор 1'],
                ['sort_order' => 1, 'author_name' => 'Автор 2'],
                ['sort_order' => 2, 'author_name' => 'Автор 3'],
            )
            ->create();

        $response = $this->actingAsStateful($user)
            ->getStatefulJson('/api/organization/reviews?organization_id='.$organization->id)
            ->assertOk()
            ->assertJsonStructure([
                'organization' => ['name', 'address', 'average_rating', 'ratings_count', 'reviews_count', 'sync_status'],
                'reviews' => [
                    'data' => [
                        ['id', 'author_name', 'published_at', 'text', 'rating'],
                    ],
                    'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
                'is_refreshing',
                'warning',
            ])
            ->assertJson([
                'organization' => [
                    'name' => 'Кафе X',
                    'average_rating' => 4.8,
                    'ratings_count' => 1200,
                    'reviews_count' => 3,
                ],
                'reviews' => [
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => 50,
                        'total' => 3,
                    ],
                ],
                'is_refreshing' => false,
                'warning' => null,
            ]);

        $this->assertCount(3, $response->json('reviews.data'));
        $this->assertSame('Автор 1', $response->json('reviews.data.0.author_name'));
    }

    public function test_user_can_view_reviews_of_another_users_organization(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $organization = Organization::factory()->for($owner)->create([
            'name' => 'Кафе X',
            'sync_status' => OrganizationSyncStatus::Completed,
        ]);

        OrganizationReview::factory()->for($organization)->create([
            'author_name' => 'Отзыв владельца',
            'sort_order' => 0,
        ]);

        $this->actingAsStateful($otherUser)
            ->getStatefulJson('/api/organization/reviews?organization_id='.$organization->id)
            ->assertOk()
            ->assertJson([
                'organization' => [
                    'name' => 'Кафе X',
                ],
                'reviews' => [
                    'meta' => [
                        'total' => 1,
                    ],
                ],
                'is_refreshing' => false,
                'warning' => null,
            ])
            ->assertJsonPath('reviews.data.0.author_name', 'Отзыв владельца');
    }

    public function test_confirm_reuses_existing_organization_by_yandex_org_id(): void
    {
        Queue::fake();

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $fixture = YandexParserFixtures::loadCollect('single_candidate');

        $this->fakeYandexParserCollect($fixture);

        $firstResolve = $this->actingAsStateful($firstUser)
            ->postStatefulJson('/api/organization/resolve', [
                'url' => self::YANDEX_URL,
            ])
            ->assertOk();

        $firstConfirm = $this->actingAsStateful($firstUser)
            ->postStatefulJson('/api/organization/confirm', [
                'session_id' => $firstResolve->json('session_id'),
                'org_id' => '1248139252',
            ])
            ->assertAccepted();

        $firstOrganizationId = $firstConfirm->json('organization.id');

        $this->fakeYandexParserCollect($fixture);

        $secondResolve = $this->actingAsStateful($secondUser)
            ->postStatefulJson('/api/organization/resolve', [
                'url' => self::YANDEX_URL,
            ])
            ->assertOk();

        $secondConfirm = $this->actingAsStateful($secondUser)
            ->postStatefulJson('/api/organization/confirm', [
                'session_id' => $secondResolve->json('session_id'),
                'org_id' => '1248139252',
            ])
            ->assertAccepted()
            ->assertJson([
                'organization' => [
                    'id' => $firstOrganizationId,
                    'yandex_org_id' => '1248139252',
                    'sync_status' => OrganizationSyncStatus::Pending->value,
                ],
            ]);

        $this->assertSame($firstOrganizationId, $secondConfirm->json('organization.id'));
        $this->assertSame(1, Organization::query()->where('yandex_org_id', '1248139252')->count());
        $this->assertDatabaseHas('organizations', [
            'id' => $firstOrganizationId,
            'user_id' => $secondUser->id,
            'yandex_org_id' => '1248139252',
        ]);
    }

    public function test_reviews_returns_warning_when_refreshing_cached_data(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create([
            'sync_status' => OrganizationSyncStatus::Pending,
        ]);

        OrganizationReview::factory()->for($organization)->create([
            'author_name' => 'Кэшированный отзыв',
            'sort_order' => 0,
        ]);

        $this->actingAsStateful($user)
            ->getStatefulJson('/api/organization/reviews?organization_id='.$organization->id)
            ->assertOk()
            ->assertJson([
                'is_refreshing' => true,
                'warning' => OrganizationReviewQueryService::REFRESHING_WARNING,
            ])
            ->assertJsonPath('reviews.meta.total', 1);
    }

    public function test_reviews_does_not_warn_when_pending_without_cached_reviews(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create([
            'sync_status' => OrganizationSyncStatus::Pending,
        ]);

        $this->actingAsStateful($user)
            ->getStatefulJson('/api/organization/reviews?organization_id='.$organization->id)
            ->assertOk()
            ->assertJson([
                'is_refreshing' => false,
                'warning' => null,
                'reviews' => [
                    'meta' => [
                        'total' => 0,
                    ],
                ],
            ]);
    }

    public function test_reviews_returns_warning_when_syncing_cached_data(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->for($user)->create([
            'sync_status' => OrganizationSyncStatus::Syncing,
        ]);

        OrganizationReview::factory()->for($organization)->create([
            'author_name' => 'Кэшированный отзыв',
            'sort_order' => 0,
        ]);

        $this->actingAsStateful($user)
            ->getStatefulJson('/api/organization/reviews?organization_id='.$organization->id)
            ->assertOk()
            ->assertJson([
                'is_refreshing' => true,
                'warning' => OrganizationReviewQueryService::REFRESHING_WARNING,
            ]);
    }
}
