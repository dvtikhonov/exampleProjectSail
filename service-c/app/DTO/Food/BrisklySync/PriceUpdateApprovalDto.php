<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

/**
 * Approval на UPDATE цены.
 */
readonly class PriceUpdateApprovalDto
{
    public function __construct(
        public string $lineKey,
        public bool $apply,
        public bool $confirmLargeDelta = false,
    ) {}

    /**
     * @param  array{line_key?: mixed, apply?: mixed, confirm_large_delta?: mixed, price?: mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            lineKey: (string) ($data['line_key'] ?? ''),
            apply: (bool) ($data['apply'] ?? false),
            confirmLargeDelta: (bool) ($data['confirm_large_delta'] ?? false),
        );
    }

    /**
     * @return array{line_key: string, apply: bool, confirm_large_delta: bool}
     */
    public function toArray(): array
    {
        return [
            'line_key' => $this->lineKey,
            'apply' => $this->apply,
            'confirm_large_delta' => $this->confirmLargeDelta,
        ];
    }
}
