<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

/**
 * Строка секции CREATE (VPS есть, Briskly нет).
 */
readonly class VpsOnlyCreateItemDto
{
    public function __construct(
        public string $lineKey,
        public string $displayName,
        public string $sourcePrice,
        public string $brisklyCreateName = '',
    ) {}

    /**
     * @param  array{
     *     line_key?: mixed,
     *     display_name?: mixed,
     *     source_price?: mixed,
     *     briskly_create_name?: mixed
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $displayName = (string) ($data['display_name'] ?? '');
        $brisklyCreateName = array_key_exists('briskly_create_name', $data)
            ? (string) $data['briskly_create_name']
            : $displayName;

        return new self(
            lineKey: (string) ($data['line_key'] ?? ''),
            displayName: $displayName,
            sourcePrice: (string) ($data['source_price'] ?? '0.00'),
            brisklyCreateName: $brisklyCreateName,
        );
    }

    /**
     * Имя для POST item/create (с весом); fallback на displayName.
     */
    public function createName(): string
    {
        return $this->brisklyCreateName !== '' ? $this->brisklyCreateName : $this->displayName;
    }

    /**
     * @return array{
     *     line_key: string,
     *     display_name: string,
     *     source_price: string,
     *     briskly_create_name: string
     * }
     */
    public function toArray(): array
    {
        return [
            'line_key' => $this->lineKey,
            'display_name' => $this->displayName,
            'source_price' => $this->sourcePrice,
            'briskly_create_name' => $this->createName(),
        ];
    }
}
