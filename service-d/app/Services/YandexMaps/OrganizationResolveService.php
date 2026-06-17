<?php

declare(strict_types=1);

namespace App\Services\YandexMaps;

use App\Contracts\YandexMapsClientInterface;
use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\DTO\YandexMaps\ResolveOrganizationDto;
use App\DTO\YandexMaps\ResolveOrganizationResultDto;
use App\Exceptions\YandexMaps\YandexMapsParserException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OrganizationResolveService
{
    private const CACHE_PREFIX = 'yandex_resolve_session:';

    private const CACHE_TTL_SECONDS = 900;

    public function __construct(
        private readonly YandexMapsClientInterface $yandexMapsClient,
    ) {}

    /**
     * @throws YandexMapsParserException
     */
    public function resolve(ResolveOrganizationDto $dto): ResolveOrganizationResultDto
    {
        $parserResult = $this->yandexMapsClient->resolve($dto->resolverUrl);

        $sessionId = (string) Str::uuid();
        $candidates = $this->filterCandidatesByClarification(
            $parserResult['candidates'],
            $dto->clarification,
        );
        $matchCount = count($candidates);

        Cache::put(
            self::CACHE_PREFIX.$sessionId,
            [
                'input_url' => $dto->inputUrl,
                'search_text' => $dto->searchText,
                'resolved_url' => $parserResult['resolved_url'],
                'candidates' => array_map(
                    static fn (OrganizationCandidateDto $candidate): array => $candidate->toArray(),
                    $candidates,
                ),
            ],
            self::CACHE_TTL_SECONDS,
        );

        return new ResolveOrganizationResultDto(
            sessionId: $sessionId,
            inputUrl: $dto->inputUrl,
            searchText: $dto->searchText,
            clarification: $dto->clarification,
            resolvedUrl: $parserResult['resolved_url'],
            matchCount: $matchCount,
            candidates: $candidates,
            autoSelected: $matchCount === 1,
        );
    }

    /**
     * @param  OrganizationCandidateDto[]  $candidates
     * @return OrganizationCandidateDto[]
     */
    private function filterCandidatesByClarification(array $candidates, ?string $clarification): array
    {
        if ($clarification === null || trim($clarification) === '') {
            return $candidates;
        }

        $needle = mb_strtolower(trim($clarification));
        $fullMatch = array_values(array_filter(
            $candidates,
            static fn (OrganizationCandidateDto $candidate): bool => self::candidateMatchesClarification($candidate, $needle),
        ));

        if ($fullMatch !== []) {
            return $fullMatch;
        }

        $tokens = self::significantClarificationTokens($needle);

        if ($tokens === []) {
            return $candidates;
        }

        $scored = [];

        foreach ($candidates as $candidate) {
            $score = self::clarificationTokenMatchScore($candidate, $tokens);

            if ($score > 0) {
                $scored[] = [
                    'candidate' => $candidate,
                    'score' => $score,
                ];
            }
        }

        if ($scored === []) {
            return $candidates;
        }

        $maxScore = max(array_column($scored, 'score'));
        $best = array_values(array_filter(
            $scored,
            static fn (array $item): bool => $item['score'] === $maxScore,
        ));

        if ($maxScore === 1 && count($best) > 1 && count($tokens) > 1) {
            return $candidates;
        }

        return array_map(
            static fn (array $item): OrganizationCandidateDto => $item['candidate'],
            $best,
        );
    }

    /**
     * @return string[]
     */
    private static function significantClarificationTokens(string $needle): array
    {
        $tokens = preg_split('/[\s,]+/u', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $significantTokens = array_values(array_filter(
            $tokens,
            static fn (string $token): bool => mb_strlen($token) >= 4,
        ));

        return $significantTokens !== [] ? $significantTokens : $tokens;
    }

    /**
     * @param  string[]  $tokens
     */
    private static function clarificationTokenMatchScore(OrganizationCandidateDto $candidate, array $tokens): int
    {
        $haystack = mb_strtolower(trim($candidate->address.' '.$candidate->name));
        $score = 0;

        foreach ($tokens as $token) {
            if ($haystack !== '' && str_contains($haystack, $token)) {
                $score++;
            }
        }

        return $score;
    }

    private static function candidateMatchesClarification(OrganizationCandidateDto $candidate, string $needle): bool
    {
        $haystack = mb_strtolower(trim($candidate->address.' '.$candidate->name));

        return $haystack !== '' && str_contains($haystack, $needle);
    }

    /**
     * @return array{input_url: string, resolved_url: string, candidates: OrganizationCandidateDto[]}
     */
    public function getSession(string $sessionId): array
    {
        /** @var array{input_url?: string, resolved_url?: string, candidates?: array<int, array<string, mixed>>}|null $session */
        $session = Cache::get(self::CACHE_PREFIX.$sessionId);

        if ($session === null) {
            throw new \App\Exceptions\Organization\OrganizationResolveSessionExpiredException;
        }

        $candidates = [];

        foreach ((array) ($session['candidates'] ?? []) as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $candidates[] = OrganizationCandidateDto::fromParserArray($candidate);
        }

        return [
            'input_url' => (string) ($session['input_url'] ?? ''),
            'resolved_url' => (string) ($session['resolved_url'] ?? ''),
            'candidates' => $candidates,
        ];
    }
}
