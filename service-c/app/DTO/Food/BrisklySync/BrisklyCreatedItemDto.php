<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

use App\Exceptions\Food\FoodDomainException;

/**
 * Минимальный ответ Briskly POST /v1/dashboard/item/create.
 */
readonly class BrisklyCreatedItemDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $price,
        public string $barcode,
        public int $categoryId,
        public int $catalogId,
        public int $status,
        public int $unitId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws FoodDomainException
     */
    public static function fromArray(array $data): self
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        if ($id <= 0) {
            throw new FoodDomainException(
                'Ответ Briskly create не содержит id созданной позиции.',
                502,
            );
        }

        return new self(
            id: $id,
            name: (string) ($data['name'] ?? ''),
            price: self::normalizePrice($data['price'] ?? '0'),
            barcode: (string) ($data['barcode'] ?? ''),
            categoryId: (int) ($data['category_id'] ?? 0),
            catalogId: (int) ($data['catalog_id'] ?? 0),
            status: (int) ($data['status'] ?? 0),
            unitId: (int) ($data['unit_id'] ?? 0),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     price: string,
     *     barcode: string,
     *     category_id: int,
     *     catalog_id: int,
     *     status: int,
     *     unit_id: int
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'barcode' => $this->barcode,
            'category_id' => $this->categoryId,
            'catalog_id' => $this->catalogId,
            'status' => $this->status,
            'unit_id' => $this->unitId,
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
