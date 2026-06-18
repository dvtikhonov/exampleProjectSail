<?php

declare(strict_types=1);

namespace App\Services\YandexMaps;

use App\DTO\YandexMaps\ResolveOrganizationDto;
use InvalidArgumentException;

/**
 * Фабрика DTO для resolve-запроса из сырого пользовательского ввода (URL или ссылка + уточнение).
 */
class ResolveOrganizationInputFactory
{
    public function __construct(
        private readonly OrganizationSearchInputValidator $searchInputValidator,
    ) {}

    /**
     * Парсит и валидирует ввод, формирует URL для yandex-parser и текст поиска.
     *
     * @throws InvalidArgumentException если ввод не прошёл валидацию
     */
    public function fromUrl(string $url): ResolveOrganizationDto
    {
        $input = $this->searchInputValidator->parse($url);

        if ($input === null) {
            throw new InvalidArgumentException('Organization search input is invalid after validation.');
        }

        return new ResolveOrganizationDto(
            inputUrl: $input->rawInput,
            resolverUrl: $this->searchInputValidator->toResolverUrl($input),
            searchText: $input->mapsSearchQuery(),
            clarification: $input->clarification,
        );
    }
}
