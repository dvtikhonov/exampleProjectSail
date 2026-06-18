<?php

declare(strict_types=1);

namespace App\Services\YandexMaps\Parsing;

use App\Contracts\OrganizationCandidateBuilderInterface;
use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\DTO\YandexMaps\ParserCollectResultDto;

/**
 * Собирает кандидатов организации из сырого ответа yandex-parser (collect).
 *
 * Два режима: прямая страница /maps/org/... и выдача поиска.
 * Источники данных: JSON network payloads, DOM harvest, page meta — мержатся через {@see OrganizationCandidateMerger}.
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
     * Одна организация: мерж API + DOM + page meta; fallback — минимальный кандидат по URL.
     *
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
     * Выдача поиска: DOM + network, дедуп по orgId, обрезка по лимиту конфига.
     *
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
     * Обходит все JSON-деревья из network payloads и мапит записи в кандидатов.
     *
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
     * Карточки из DOM harvest ({@see DomHarvestMapper}).
     *
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
     * Сначала DOM как база, затем network; для счётчиков рейтинга предпочитаем DOM.
     *
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
            $mergedCandidate = $this->candidateMerger->merge($candidate, $existing) ?? $candidate;
            $merged[$candidate->orgId] = $this->candidateMerger->preferDomRatingCounts($mergedCandidate, $existing);
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

    /** Последний fallback для direct org, когда API/DOM/page meta не дали полного кандидата. */
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
