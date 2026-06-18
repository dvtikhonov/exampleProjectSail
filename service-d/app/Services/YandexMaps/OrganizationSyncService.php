<?php

declare(strict_types=1);

namespace App\Services\YandexMaps;

use App\Contracts\OrganizationRepositoryInterface;
use App\Contracts\OrganizationReviewRepositoryInterface;
use App\Contracts\YandexMapsClientInterface;
use App\Enums\OrganizationSyncStatus;
use App\Exceptions\YandexMaps\YandexMapsParserException;
use Illuminate\Support\Facades\Log;

class OrganizationSyncService
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $organizationRepository,
        private readonly OrganizationReviewRepositoryInterface $organizationReviewRepository,
        private readonly YandexMapsClientInterface $yandexMapsClient,
    ) {}

    public function sync(int $organizationId): void
    {
        $organization = $this->organizationRepository->findById($organizationId);

        if ($organization === null) {
            return;
        }

        $hasCachedReviews = $this->organizationReviewRepository->countByOrganization($organizationId) > 0;
        $stopAnchors = $hasCachedReviews
            ? $this->organizationReviewRepository->findSyncStopAnchors($organizationId)
            : [];

        $this->organizationRepository->updateSyncStatus(
            organizationId: $organizationId,
            status: OrganizationSyncStatus::Syncing,
            syncError: null,
        );

        try {
            $result = $this->yandexMapsClient->syncReviews(
                orgId: $organization->yandex_org_id,
                canonicalUrl: $organization->canonical_url,
                stopAnchors: $stopAnchors,
            );

            $this->organizationRepository->updateFromParsedMeta($organizationId, $result['org']);

            if ($hasCachedReviews) {
                $this->organizationReviewRepository->mergeAndReorderForOrganization($organizationId, $result['reviews']);
            } else {
                $this->organizationReviewRepository->replaceForOrganization($organizationId, $result['reviews']);
            }

            $this->organizationRepository->markSyncCompleted($organizationId);
        } catch (\Throwable $exception) {
            $message = $exception instanceof YandexMapsParserException
                ? $exception->getMessage()
                : 'Не удалось синхронизировать отзывы.';

            Log::error('Organization reviews sync failed.', [
                'organization_id' => $organizationId,
                'message' => $message,
            ]);

            $this->organizationRepository->updateSyncStatus(
                organizationId: $organizationId,
                status: OrganizationSyncStatus::Failed,
                syncError: $message,
            );
        }
    }
}
