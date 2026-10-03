<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

/**
 * Отчёт apply в Briskly.
 */
readonly class BrisklySyncApplyReportDto
{
    /**
     * @param  list<array{line_key: string, message: string}>  $errors
     * @param  list<array{line_key: string, briskly_item_id: int, barcode: string, name: string}>  $createdItems
     */
    public function __construct(
        public int $updated,
        public int $created,
        public int $skippedUnchecked,
        public int $skippedEqual,
        public array $errors,
        public array $createdItems = [],
    ) {}

    /**
     * @param  array{
     *     updated?: mixed,
     *     created?: mixed,
     *     skipped_unchecked?: mixed,
     *     skipped_equal?: mixed,
     *     errors?: mixed,
     *     created_items?: mixed
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        $errors = [];
        foreach ($data['errors'] ?? [] as $error) {
            if (! is_array($error)) {
                continue;
            }
            $errors[] = [
                'line_key' => (string) ($error['line_key'] ?? ''),
                'message' => (string) ($error['message'] ?? ''),
            ];
        }

        $createdItems = [];
        foreach ($data['created_items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $createdItems[] = [
                'line_key' => (string) ($item['line_key'] ?? ''),
                'briskly_item_id' => (int) ($item['briskly_item_id'] ?? 0),
                'barcode' => (string) ($item['barcode'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
            ];
        }

        return new self(
            updated: (int) ($data['updated'] ?? 0),
            created: (int) ($data['created'] ?? 0),
            skippedUnchecked: (int) ($data['skipped_unchecked'] ?? 0),
            skippedEqual: (int) ($data['skipped_equal'] ?? 0),
            errors: $errors,
            createdItems: $createdItems,
        );
    }

    /**
     * @return array{
     *     updated: int,
     *     created: int,
     *     skipped_unchecked: int,
     *     skipped_equal: int,
     *     errors: list<array{line_key: string, message: string}>,
     *     created_items: list<array{line_key: string, briskly_item_id: int, barcode: string, name: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'updated' => $this->updated,
            'created' => $this->created,
            'skipped_unchecked' => $this->skippedUnchecked,
            'skipped_equal' => $this->skippedEqual,
            'errors' => $this->errors,
            'created_items' => $this->createdItems,
        ];
    }
}
