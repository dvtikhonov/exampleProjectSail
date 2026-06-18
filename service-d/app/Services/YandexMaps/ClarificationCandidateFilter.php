<?php

declare(strict_types=1);

namespace App\Services\YandexMaps;

use App\DTO\YandexMaps\OrganizationCandidateDto;

/**
 * Сужает список кандидатов организации по тексту уточнения (город, улица и т.д.).
 */
class ClarificationCandidateFilter
{
    /**
     * Сначала точное вхождение уточнения; иначе — скoring по токенам с защитой от ложного сужения.
     *
     * @param  OrganizationCandidateDto[]  $candidates
     * @return OrganizationCandidateDto[]
     */
    public function filter(array $candidates, ?string $clarification): array
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
     * Токены длиной ≥ 4 символов; если таких нет — все токены из уточнения.
     *
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
     * Число совпавших токенов уточнения в address + name кандидата.
     *
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

    /** Полное вхождение строки уточнения в address + name (без учёта регистра). */
    private static function candidateMatchesClarification(OrganizationCandidateDto $candidate, string $needle): bool
    {
        $haystack = mb_strtolower(trim($candidate->address.' '.$candidate->name));

        return $haystack !== '' && str_contains($haystack, $needle);
    }
}
