<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

/**
 * Approval на CREATE в Briskly.
 */
readonly class CreateApprovalDto
{
    public function __construct(
        public string $lineKey,
        public bool $apply,
        public ?int $brisklyCategoryId = null,
    ) {}

    /**
     * @param  array{line_key?: mixed, apply?: mixed, briskly_category_id?: mixed, price?: mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        $categoryId = $data['briskly_category_id'] ?? null;

        return new self(
            lineKey: (string) ($data['line_key'] ?? ''),
            apply: (bool) ($data['apply'] ?? false),
            brisklyCategoryId: $categoryId === null || $categoryId === '' ? null : (int) $categoryId,
        );
    }

    /**
     * @return array{line_key: string, apply: bool, briskly_category_id: int|null}
     */
    public function toArray(): array
    {
        return [
            'line_key' => $this->lineKey,
            'apply' => $this->apply,
            'briskly_category_id' => $this->brisklyCategoryId,
        ];
    }
}
