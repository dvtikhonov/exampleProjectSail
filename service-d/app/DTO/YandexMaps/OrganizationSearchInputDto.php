<?php

declare(strict_types=1);

namespace App\DTO\YandexMaps;

/**
 * Разобранный ввод пользователя: ссылка и опциональное уточнение.
 */
readonly class OrganizationSearchInputDto
{
    public function __construct(
        public string $rawInput,
        public string $linkPart,
        public ?string $clarification,
        public bool $isYandexMapsUrl,
    ) {}

    public function searchText(): string
    {
        if ($this->clarification === null || $this->clarification === '') {
            return $this->linkPart;
        }

        return trim($this->linkPart.' '.$this->clarification);
    }

    /**
     * Текст для поиска на Яндекс.Картах: ссылка и уточнение как ввёл пользователь.
     */
    public function mapsSearchQuery(): string
    {
        return $this->searchText();
    }
}
