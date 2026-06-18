<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\DTO\YandexMaps\ParsedReviewDto;
use App\Models\Organization;
use App\Models\OrganizationReview;
use App\Repositories\Organization\EloquentOrganizationReviewRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EloquentOrganizationReviewRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentOrganizationReviewRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(EloquentOrganizationReviewRepository::class);
    }

    public function test_merge_and_reorder_assigns_sort_order_from_yandex_index(): void
    {
        $organization = Organization::factory()->create();

        $this->repository->mergeAndReorderForOrganization($organization->id, [
            new ParsedReviewDto(
                externalId: 'rev-b',
                authorName: 'Б',
                publishedAt: Carbon::parse('2024-06-01'),
                text: 'Второй',
                rating: 4,
            ),
            new ParsedReviewDto(
                externalId: 'rev-a',
                authorName: 'А',
                publishedAt: Carbon::parse('2024-06-02'),
                text: 'Первый',
                rating: 5,
            ),
        ]);

        $this->assertSame(
            ['rev-b', 'rev-a'],
            OrganizationReview::query()
                ->where('organization_id', $organization->id)
                ->orderBy('sort_order')
                ->pluck('external_review_id')
                ->all(),
        );
        $this->assertDatabaseHas('organization_reviews', [
            'organization_id' => $organization->id,
            'external_review_id' => 'rev-b',
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('organization_reviews', [
            'organization_id' => $organization->id,
            'external_review_id' => 'rev-a',
            'sort_order' => 1,
        ]);
    }

    public function test_merge_and_reorder_preserves_orphan_reviews_after_yandex_block(): void
    {
        $organization = Organization::factory()->create();

        OrganizationReview::factory()->for($organization)->create([
            'external_review_id' => 'orphan-1',
            'author_name' => 'Старый 1',
            'sort_order' => 0,
        ]);
        OrganizationReview::factory()->for($organization)->create([
            'external_review_id' => 'orphan-2',
            'author_name' => 'Старый 2',
            'sort_order' => 1,
        ]);

        $this->repository->mergeAndReorderForOrganization($organization->id, [
            new ParsedReviewDto(
                externalId: 'rev-001',
                authorName: 'Яндекс',
                publishedAt: Carbon::parse('2024-06-15'),
                text: 'Новый',
                rating: 5,
            ),
        ]);

        $ordered = OrganizationReview::query()
            ->where('organization_id', $organization->id)
            ->orderBy('sort_order')
            ->pluck('external_review_id')
            ->all();

        $this->assertSame(['rev-001', 'orphan-1', 'orphan-2'], $ordered);
        $this->assertDatabaseHas('organization_reviews', [
            'organization_id' => $organization->id,
            'external_review_id' => 'orphan-1',
            'author_name' => 'Старый 1',
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('organization_reviews', [
            'organization_id' => $organization->id,
            'external_review_id' => 'orphan-2',
            'author_name' => 'Старый 2',
            'sort_order' => 2,
        ]);
    }

    public function test_merge_and_reorder_updates_existing_yandex_review(): void
    {
        $organization = Organization::factory()->create();

        OrganizationReview::factory()->for($organization)->create([
            'external_review_id' => 'rev-001',
            'author_name' => 'Старое имя',
            'text' => 'Старый текст',
            'rating' => 3,
            'sort_order' => 5,
        ]);

        $this->repository->mergeAndReorderForOrganization($organization->id, [
            new ParsedReviewDto(
                externalId: 'rev-001',
                authorName: 'Новое имя',
                publishedAt: Carbon::parse('2024-07-01'),
                text: 'Новый текст',
                rating: 5,
            ),
        ]);

        $this->assertSame(1, OrganizationReview::query()->where('organization_id', $organization->id)->count());
        $this->assertDatabaseHas('organization_reviews', [
            'organization_id' => $organization->id,
            'external_review_id' => 'rev-001',
            'author_name' => 'Новое имя',
            'text' => 'Новый текст',
            'rating' => 5,
            'sort_order' => 0,
        ]);
    }

    public function test_find_sync_stop_anchors_returns_top_reviews_by_sort_order(): void
    {
        $organization = Organization::factory()->create();

        OrganizationReview::factory()->for($organization)->create([
            'external_review_id' => 'rev-third',
            'sort_order' => 2,
        ]);
        OrganizationReview::factory()->for($organization)->create([
            'external_review_id' => 'rev-first',
            'sort_order' => 0,
        ]);
        OrganizationReview::factory()->for($organization)->create([
            'external_review_id' => 'rev-second',
            'sort_order' => 1,
        ]);

        $this->assertSame(
            ['rev-first', 'rev-second', 'rev-third'],
            $this->repository->findSyncStopAnchors($organization->id),
        );
    }

    public function test_replace_for_organization_deletes_previous_reviews(): void
    {
        $organization = Organization::factory()->create();

        OrganizationReview::factory()->for($organization)->create([
            'external_review_id' => 'old-rev',
            'sort_order' => 0,
        ]);

        $this->repository->replaceForOrganization($organization->id, [
            new ParsedReviewDto(
                externalId: 'rev-001',
                authorName: 'Новый',
                publishedAt: Carbon::parse('2024-06-15'),
                text: 'Текст',
                rating: 4,
            ),
        ]);

        $this->assertSame(1, OrganizationReview::query()->where('organization_id', $organization->id)->count());
        $this->assertDatabaseMissing('organization_reviews', [
            'organization_id' => $organization->id,
            'external_review_id' => 'old-rev',
        ]);
        $this->assertDatabaseHas('organization_reviews', [
            'organization_id' => $organization->id,
            'external_review_id' => 'rev-001',
            'sort_order' => 0,
        ]);
    }
}
