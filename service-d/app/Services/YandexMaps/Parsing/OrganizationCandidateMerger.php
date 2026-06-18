<?php

declare(strict_types=1);

namespace App\Services\YandexMaps\Parsing;

use App\DTO\YandexMaps\OrganizationCandidateDto;

/**
 * Merges and deduplicates organization candidates from multiple sources.
 */
class OrganizationCandidateMerger
{
    public function __construct(
        private readonly OrganizationRecordMapper $recordMapper,
    ) {}

    /**
     * Prefer API/network fields over DOM fallbacks.
     */
    public function merge(
        ?OrganizationCandidateDto $primary,
        ?OrganizationCandidateDto $fallback,
        ?string $orgId = null,
    ): ?OrganizationCandidateDto {
        if ($primary === null && $fallback === null) {
            return null;
        }

        if ($primary === null) {
            return $fallback;
        }

        if ($fallback === null) {
            if ($orgId !== null) {
                return $this->withOrgId($primary, $orgId);
            }

            return $primary;
        }

        $resolvedOrgId = $orgId ?? $primary->orgId ?: $fallback->orgId;

        return new OrganizationCandidateDto(
            orgId: $resolvedOrgId,
            name: $this->pickMergedName($primary->name, $fallback->name),
            address: $primary->address !== '' ? $primary->address : $fallback->address,
            averageRating: $primary->averageRating ?? $fallback->averageRating,
            reviewsCount: $primary->reviewsCount ?? $fallback->reviewsCount,
            ratingsCount: $primary->ratingsCount ?? $fallback->ratingsCount,
            canonicalUrl: $primary->canonicalUrl !== '' ? $primary->canonicalUrl : $fallback->canonicalUrl,
        );
    }

    /**
     * @param  OrganizationCandidateDto[]  $candidates
     * @return OrganizationCandidateDto[]
     */
    public function dedupe(array $candidates): array
    {
        /** @var array<string, OrganizationCandidateDto> $map */
        $map = [];

        foreach ($candidates as $candidate) {
            $existing = $map[$candidate->orgId] ?? null;
            $map[$candidate->orgId] = $this->merge($candidate, $existing) ?? $candidate;
        }

        return array_values(array_filter(
            $map,
            fn (OrganizationCandidateDto $candidate): bool => $this->recordMapper->isPlausibleOrgName($candidate->name),
        ));
    }

    private function pickMergedName(string $primaryName, string $fallbackName): string
    {
        if ($this->recordMapper->isPlausibleOrgName($primaryName)) {
            return $primaryName;
        }

        if ($this->recordMapper->isPlausibleOrgName($fallbackName)) {
            return $fallbackName;
        }

        return $primaryName !== '' ? $primaryName : $fallbackName;
    }

    private function withOrgId(OrganizationCandidateDto $candidate, string $orgId): OrganizationCandidateDto
    {
        if ($candidate->orgId === $orgId) {
            return $candidate;
        }

        return new OrganizationCandidateDto(
            orgId: $orgId,
            name: $candidate->name,
            address: $candidate->address,
            averageRating: $candidate->averageRating,
            reviewsCount: $candidate->reviewsCount,
            ratingsCount: $candidate->ratingsCount,
            canonicalUrl: $candidate->canonicalUrl,
        );
    }
}
