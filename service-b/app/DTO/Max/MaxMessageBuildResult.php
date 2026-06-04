<?php

namespace App\DTO\Max;

readonly class MaxMessageBuildResult
{
    public function __construct(
        public string $text,
        public int $totalRows,
        public int $includedRows,
        public bool $truncated,
    ) {}
}
