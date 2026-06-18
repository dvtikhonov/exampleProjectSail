<?php

declare(strict_types=1);

namespace App\DTO\YandexMaps;

readonly class DomOrgHarvestDto
{
    public function __construct(
        public string $href,
        public string $linkText,
        public string $cardText,
        public string $ratingAriaLabel,
        public string $metaText,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromParserArray(array $data): self
    {
        return new self(
            href: (string) ($data['href'] ?? ''),
            linkText: (string) ($data['link_text'] ?? ''),
            cardText: (string) ($data['card_text'] ?? ''),
            ratingAriaLabel: (string) ($data['rating_aria_label'] ?? ''),
            metaText: (string) ($data['meta_text'] ?? ''),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'href' => $this->href,
            'link_text' => $this->linkText,
            'card_text' => $this->cardText,
            'rating_aria_label' => $this->ratingAriaLabel,
            'meta_text' => $this->metaText,
        ];
    }
}
