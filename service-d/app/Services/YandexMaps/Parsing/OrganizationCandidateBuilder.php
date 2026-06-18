<?php

declare(strict_types=1);

namespace App\Services\YandexMaps\Parsing;

use App\Contracts\OrganizationCandidateBuilderInterface;
use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\DTO\YandexMaps\ParserCollectResultDto;

/**
 * Builds organization candidates from raw parser collect payloads.
 */
class OrganizationCandidateBuilder implements OrganizationCandidateBuilderInterface
{
    public function __construct(
        private readonly JsonTreeWalker $jsonTreeWalker,
        private readonly YandexUrlHelper $urlHelper,
        private readonly OrganizationRecordMapper $recordMapper,
        private readonly DomHarvestMapper $domHarvestMapper,
        private readonly OrganizationCandidateMerger $candidateMerger,
    ) {}

    /**
     * @return OrganizationCandidateDto[]
     */
    public function build(ParserCollectResultDto $collect): array
    {
        if ($collect->isDirectOrg) {
            return $this->buildDirectOrgCandidates($collect);
        }

        return $this->buildSearchCandidates($collect);
    }

    /**
     * @return OrganizationCandidateDto[]
     */
    private function buildDirectOrgCandidates(ParserCollectResultDto $collect): array
    {
        $orgId = $collect->directOrgId
            ?? $this->urlHelper->extractOrgIdFromUrl($collect->resolvedUrl);

        if ($orgId === null) {
            return [];
        }

        $origin = $this->urlHelper->safeOrigin($collect->resolvedUrl);
        $networkCandidates = $this->extractCandidatesFromPayloads($collect->networkPayloads, $origin);
        $apiCandidate = $this->findCandidateByOrgId($networkCandidates, $orgId);

        $domCandidate = $this->findDomCandidateForOrgId($collect, $origin, $orgId);
        $pageMetaCandidate = $this->domHarvestMapper->mapPageMeta(
            $collect->pageMeta,
            $collect->resolvedUrl,
            $orgId,
        );

        $merged = $this->candidateMerger->merge(
            $this->candidateMerger->merge($apiCandidate, $domCandidate, $orgId),
            $pageMetaCandidate,
            $orgId,
        );

        if ($merged !== null) {
            return [$merged];
        }

        return [$this->buildFallbackDirectCandidate($collect, $orgId)];
    }

    /**
     * @return OrganizationCandidateDto[]
     */
    private function buildSearchCandidates(ParserCollectResultDto $collect): array
    {
        $origin = $this->urlHelper->safeOrigin($collect->resolvedUrl);
        $fromNetwork = $this->extractCandidatesFromPayloads($collect->networkPayloads, $origin);
        $fromDom = $this->mapDomHarvest($collect, $origin);
        $merged = $this->mergeSearchSources($fromDom, $fromNetwork);
        $limit = (int) config('services.yandex_parser.resolve_candidate_limit', 30);

        return array_slice($merged, 0, $limit);
    }

    /**
     * @param  array<int, mixed>  $payloads
     * @return OrganizationCandidateDto[]
     */
    private function extractCandidatesFromPayloads(array $payloads, string $origin): array
    {
        $candidates = [];

        foreach ($payloads as $payload) {
            $this->jsonTreeWalker->walk($payload, function (array $record) use (&$candidates, $origin): void {
                $candidate = $this->recordMapper->mapRecordToCandidate($record, $origin);

                if ($candidate !== null) {
                    $candidates[] = $candidate;
                }
            });
        }

        return $this->candidateMerger->dedupe($candidates);
    }

    /**
     * @return OrganizationCandidateDto[]
     */
    private function mapDomHarvest(ParserCollectResultDto $collect, string $origin): array
    {
        $candidates = [];

        foreach ($collect->domHarvest as $harvest) {
            $candidate = $this->domHarvestMapper->mapHarvest($harvest, $origin);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    /**
     * @param  OrganizationCandidateDto[]  $fromDom
     * @param  OrganizationCandidateDto[]  $fromNetwork
     * @return OrganizationCandidateDto[]
     */
    private function mergeSearchSources(array $fromDom, array $fromNetwork): array
    {
        /** @var array<string, OrganizationCandidateDto> $merged */
        $merged = [];

        foreach ($fromDom as $candidate) {
            $existing = $merged[$candidate->orgId] ?? null;
            $merged[$candidate->orgId] = $this->candidateMerger->merge($existing, $candidate) ?? $candidate;
        }

        foreach ($fromNetwork as $candidate) {
            $existing = $merged[$candidate->orgId] ?? null;
            $merged[$candidate->orgId] = $this->candidateMerger->merge($candidate, $existing) ?? $candidate;
        }

        return array_values(array_filter(
            $merged,
            fn (OrganizationCandidateDto $candidate): bool => $this->recordMapper->isPlausibleOrgName($candidate->name),
        ));
    }

    private function findDomCandidateForOrgId(
        ParserCollectResultDto $collect,
        string $origin,
        string $orgId,
    ): ?OrganizationCandidateDto {
        foreach ($collect->domHarvest as $harvest) {
            $candidate = $this->domHarvestMapper->mapHarvest($harvest, $origin);

            if ($candidate !== null && $candidate->orgId === $orgId) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  OrganizationCandidateDto[]  $candidates
     */
    private function findCandidateByOrgId(array $candidates, string $orgId): ?OrganizationCandidateDto
    {
        foreach ($candidates as $candidate) {
            if ($candidate->orgId === $orgId) {
                return $candidate;
            }
        }

        return null;
    }

    private function buildFallbackDirectCandidate(ParserCollectResultDto $collect, string $orgId): OrganizationCandidateDto
    {
        $pageMetaCandidate = $this->domHarvestMapper->mapPageMeta(
            $collect->pageMeta,
            $collect->resolvedUrl,
            $orgId,
        );

        if ($pageMetaCandidate !== null) {
            return $pageMetaCandidate;
        }

        return new OrganizationCandidateDto(
            orgId: $orgId,
            name: '',
            address: trim($collect->pageMeta->addressText),
            averageRating: null,
            reviewsCount: null,
            ratingsCount: null,
            canonicalUrl: $this->urlHelper->normalizeOrgUrl($collect->resolvedUrl, $orgId),
        );
    }
}
