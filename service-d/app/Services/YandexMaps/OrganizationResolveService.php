<?php

declare(strict_types=1);

namespace App\Services\YandexMaps;

use App\Contracts\OrganizationCandidateBuilderInterface;
use App\Contracts\ResolveSessionStoreInterface;
use App\Contracts\YandexMapsClientInterface;
use App\DTO\YandexMaps\ResolveOrganizationDto;
use App\DTO\YandexMaps\ResolveOrganizationResultDto;
use App\Exceptions\YandexMaps\YandexMapsParserException;
use Illuminate\Support\Str;

/**
 * Основной сценарий «разрешения» организации по ссылке или текстовому запросу.
 *
 * Вызывает yandex-parser, собирает кандидатов, фильтрует по уточнению,
 * сохраняет промежуточный результат в сессию (кеш) и возвращает список для выбора пользователем.
 */
class OrganizationResolveService
{
    /** Время жизни resolve-сессии в кеше (15 минут). */
    private const SESSION_TTL_SECONDS = 900;

    public function __construct(
        private readonly YandexMapsClientInterface $yandexMapsClient,
        private readonly OrganizationCandidateBuilderInterface $candidateBuilder,
        private readonly ClarificationCandidateFilter $clarificationFilter,
        private readonly ResolveSessionStoreInterface $sessionStore,
    ) {}

    /**
     * @throws YandexMapsParserException при ошибке парсера или недоступности yandex-parser
     */
    public function resolve(ResolveOrganizationDto $dto): ResolveOrganizationResultDto
    {
        $collect = $this->yandexMapsClient->collect($dto->resolverUrl);
        $candidates = $this->candidateBuilder->build($collect);
        $candidates = $this->clarificationFilter->filter($candidates, $dto->clarification);

        $sessionId = (string) Str::uuid();
        $matchCount = count($candidates);

        $this->sessionStore->put(
            sessionId: $sessionId,
            inputUrl: $dto->inputUrl,
            searchText: $dto->searchText,
            resolvedUrl: $collect->resolvedUrl,
            candidates: $candidates,
            ttlSeconds: self::SESSION_TTL_SECONDS,
        );

        return new ResolveOrganizationResultDto(
            sessionId: $sessionId,
            inputUrl: $dto->inputUrl,
            searchText: $dto->searchText,
            clarification: $dto->clarification,
            resolvedUrl: $collect->resolvedUrl,
            matchCount: $matchCount,
            candidates: $candidates,
            autoSelected: $matchCount === 1,
        );
    }
}
