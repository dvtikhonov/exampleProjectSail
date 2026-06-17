<?php

declare(strict_types=1);

namespace App\Repositories\Organization;

use App\Contracts\OrganizationRepositoryInterface;
use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\DTO\YandexMaps\ParsedOrganizationMetaDto;
use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;

class EloquentOrganizationRepository implements OrganizationRepositoryInterface
{
    public function findByUserId(int $userId): ?Organization
    {
        return Organization::query()
            ->where('user_id', $userId)
            ->first();
    }

    public function findById(int $organizationId): ?Organization
    {
        return Organization::query()->find($organizationId);
    }

    public function upsertForUser(
        int $userId,
        string $sourceUrl,
        OrganizationCandidateDto $candidate,
    ): Organization {
        return Organization::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'source_url' => $sourceUrl,
                'canonical_url' => $candidate->canonicalUrl,
                'yandex_org_id' => $candidate->orgId,
                'name' => $candidate->name,
                'address' => $candidate->address,
                'average_rating' => $candidate->averageRating,
                'ratings_count' => $candidate->ratingsCount,
                'reviews_count' => $candidate->reviewsCount,
                'sync_status' => OrganizationSyncStatus::Pending,
                'sync_error' => null,
                'last_synced_at' => null,
            ],
        );
    }

    public function updateSyncStatus(
        int $organizationId,
        OrganizationSyncStatus $status,
        ?string $syncError = null,
    ): void {
        Organization::query()
            ->whereKey($organizationId)
            ->update([
                'sync_status' => $status->value,
                'sync_error' => $syncError,
            ]);
    }

    public function updateFromParsedMeta(int $organizationId, ParsedOrganizationMetaDto $meta): void
    {
        Organization::query()
            ->whereKey($organizationId)
            ->update([
                'canonical_url' => $meta->canonicalUrl,
                'yandex_org_id' => $meta->orgId,
                'name' => $meta->name,
                'address' => $meta->address,
                'average_rating' => $meta->averageRating,
                'ratings_count' => $meta->ratingsCount,
                'reviews_count' => $meta->reviewsCount,
            ]);
    }

    public function markSyncCompleted(int $organizationId): void
    {
        Organization::query()
            ->whereKey($organizationId)
            ->update([
                'sync_status' => OrganizationSyncStatus::Completed->value,
                'sync_error' => null,
                'last_synced_at' => now(),
            ]);
    }
}
