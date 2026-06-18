<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\DTO\YandexMaps\ParsedOrganizationMetaDto;
use App\Enums\OrganizationSyncStatus;
use App\Models\Organization;

interface OrganizationRepositoryInterface
{
    public function findByUserId(int $userId): ?Organization;

    public function findByYandexOrgId(string $yandexOrgId): ?Organization;

    public function findById(int $organizationId): ?Organization;

    public function upsertForUser(
        int $userId,
        string $sourceUrl,
        OrganizationCandidateDto $candidate,
    ): Organization;

    public function updateSyncStatus(
        int $organizationId,
        OrganizationSyncStatus $status,
        ?string $syncError = null,
    ): void;

    public function updateFromParsedMeta(int $organizationId, ParsedOrganizationMetaDto $meta): void;

    public function markSyncCompleted(int $organizationId): void;
}
