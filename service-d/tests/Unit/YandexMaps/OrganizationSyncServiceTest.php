<?php

declare(strict_types=1);

namespace Tests\Unit\YandexMaps;

use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;
use App\Models\OrganizationReview;
use App\Services\YandexMaps\OrganizationSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesYandexParser;
use Tests\Support\YandexParserFixtures;
use Tests\TestCase;

class OrganizationSyncServiceTest extends TestCase
{
    use FakesYandexParser;
    use RefreshDatabase;

    private OrganizationSyncService $syncService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpYandexParserConfig();
        $this->syncService = $this->app->make(OrganizationSyncService::class);
    }

    public function test_sync_replaces_when_no_reviews(): void
    {
        $organization = Organization::factory()->create([
            'yandex_org_id' => '1248139252',
            'canonical_url' => 'https://yandex.ru/maps/org/test-cafe/1248139252/',
            'sync_status' => OrganizationSyncStatus::Pending,
        ]);

        $this->fakeYandexParserSyncReviews(YandexParserFixtures::load('sync_reviews'));

        $this->syncService->sync($organization->id);

        $organization->refresh();

        $this->assertSame(OrganizationSyncStatus::Completed, $organization->sync_status);
        $this->assertSame(2, $organization->reviews()->count());
        $this->assertDatabaseHas('organization_reviews', [
            'organization_id' => $organization->id,
            'external_review_id' => 'rev-001',
            'author_name' => 'Иван Петров',
        ]);
    }

    public function test_sync_merges_when_reviews_exist(): void
    {
        $organization = Organization::factory()->create([
            'yandex_org_id' => '1248139252',
            'canonical_url' => 'https://yandex.ru/maps/org/test-cafe/1248139252/',
            'sync_status' => OrganizationSyncStatus::Completed,
        ]);

        OrganizationReview::factory()->for($organization)->create([
            'external_review_id' => 'orphan-rev',
            'author_name' => 'Старый отзыв',
            'sort_order' => 0,
        ]);

        $this->fakeYandexParserSyncReviews(YandexParserFixtures::load('sync_reviews'));

        $this->syncService->sync($organization->id);

        $organization->refresh();

        $this->assertSame(OrganizationSyncStatus::Completed, $organization->sync_status);
        $this->assertSame(3, $organization->reviews()->count());
        $this->assertDatabaseHas('organization_reviews', [
            'organization_id' => $organization->id,
            'external_review_id' => 'orphan-rev',
            'author_name' => 'Старый отзыв',
        ]);
        $this->assertDatabaseHas('organization_reviews', [
            'organization_id' => $organization->id,
            'external_review_id' => 'rev-001',
        ]);
    }

    public function test_sync_passes_stop_anchors_when_reviews_exist(): void
    {
        $organization = Organization::factory()->create([
            'yandex_org_id' => '1248139252',
            'canonical_url' => 'https://yandex.ru/maps/org/test-cafe/1248139252/',
            'sync_status' => OrganizationSyncStatus::Completed,
        ]);

        OrganizationReview::factory()->for($organization)->create([
            'external_review_id' => 'cached-1',
            'sort_order' => 0,
        ]);
        OrganizationReview::factory()->for($organization)->create([
            'external_review_id' => 'cached-2',
            'sort_order' => 1,
        ]);
        OrganizationReview::factory()->for($organization)->create([
            'external_review_id' => 'cached-3',
            'sort_order' => 2,
        ]);

        $this->fakeYandexParserSyncReviews(YandexParserFixtures::load('sync_reviews'));

        $this->syncService->sync($organization->id);

        Http::assertSent(function ($request): bool {
            if (! str_ends_with($request->url(), '/sync-reviews')) {
                return false;
            }

            return ($request->data()['stop_anchors'] ?? null) === ['cached-1', 'cached-2', 'cached-3'];
        });
    }
}
