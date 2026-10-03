<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

/**
 * Элемент snapshot каталога Briskly (id + name + price).
 */
readonly class BrisklySnapshotItemDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $price,
    ) {}

    /**
     * @param  array{id?: mixed, name?: mixed, price?: mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            price: self::normalizePrice($data['price'] ?? '0'),
        );
    }

    /**
     * @return array{id: int, name: string, price: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
        ];
    }

    private static function normalizePrice(mixed $price): string
    {
        if (is_int($price) || is_float($price)) {
            return number_format((float) $price, 2, '.', '');
        }

        $raw = str_replace(',', '.', trim((string) $price));
        if ($raw === '' || ! is_numeric($raw)) {
            return '0.00';
        }

        return number_format((float) $raw, 2, '.', '');
    }
}
