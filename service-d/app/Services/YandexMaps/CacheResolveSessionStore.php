<?php

declare(strict_types=1);

namespace App\Services\YandexMaps;

use App\Contracts\ResolveSessionStoreInterface;
use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\DTO\YandexMaps\ResolveSessionDto;
use App\Exceptions\Organization\OrganizationResolveSessionExpiredException;
use Illuminate\Support\Facades\Cache;

/**
 * Хранение resolve-сессий в Laravel Cache (Redis/file — по конфигу приложения).
 *
 * Сессия связывает sessionId с исходным URL, списком кандидатов и resolved URL парсера.
 */
class CacheResolveSessionStore implements ResolveSessionStoreInterface
{
    private const CACHE_PREFIX = 'yandex_resolve_session:';

    /**
     * Сохраняет сессию с TTL; кандидаты сериализуются через {@see OrganizationCandidateDto::toArray()}.
     *
     * @param  OrganizationCandidateDto[]  $candidates
     */
    public function put(
        string $sessionId,
        string $inputUrl,
        string $searchText,
        string $resolvedUrl,
        array $candidates,
        int $ttlSeconds,
    ): void {
        Cache::put(
            self::CACHE_PREFIX.$sessionId,
            [
                'input_url' => $inputUrl,
                'search_text' => $searchText,
                'resolved_url' => $resolvedUrl,
                'candidates' => array_map(
                    static fn (OrganizationCandidateDto $candidate): array => $candidate->toArray(),
                    $candidates,
                ),
            ],
            $ttlSeconds,
        );
    }

    /**
     * @throws OrganizationResolveSessionExpiredException если ключ отсутствует или истёк TTL
     */
    public function get(string $sessionId): ResolveSessionDto
    {
        /** @var array{input_url?: string, resolved_url?: string, candidates?: array<int, array<string, mixed>>}|null $session */
        $session = Cache::get(self::CACHE_PREFIX.$sessionId);

        if ($session === null) {
            throw new OrganizationResolveSessionExpiredException;
        }

        $candidates = [];

        foreach ((array) ($session['candidates'] ?? []) as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $candidates[] = OrganizationCandidateDto::fromParserArray($candidate);
        }

        return new ResolveSessionDto(
            inputUrl: (string) ($session['input_url'] ?? ''),
            resolvedUrl: (string) ($session['resolved_url'] ?? ''),
            candidates: $candidates,
        );
    }
}
