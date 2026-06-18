<?php

declare(strict_types=1);

namespace App\DTO\YandexMaps;

readonly class PageMetaDto
{
    public function __construct(
        public string $title,
        public string $headerText,
        public string $addressText,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromParserArray(array $data): self
    {
        return new self(
            title: (string) ($data['title'] ?? ''),
            headerText: (string) ($data['header_text'] ?? ''),
            addressText: (string) ($data['address_text'] ?? ''),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'header_text' => $this->headerText,
            'address_text' => $this->addressText,
        ];
    }
}
