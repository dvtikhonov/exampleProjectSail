<?php

namespace App\DTO\SalesOutlets;

readonly class ReportDeliveryResult
{
    public function __construct(
        public ?string $filePath = null,
    ) {}

    public static function none(): self
    {
        return new self();
    }

    public static function withFile(string $path): self
    {
        return new self($path);
    }
}
