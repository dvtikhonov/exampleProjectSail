<?php

namespace App\DTO\SalesOutlets;

use App\Models\SalesOutletMailJob;

readonly class SalesOutletMailJobDto
{
    public function __construct(
        public string $uuid,
        public string $status,
        public ?string $errorMessage,
    ) {}

    public static function fromModel(SalesOutletMailJob $mailJob): self
    {
        return new self(
            uuid: $mailJob->uuid,
            status: $mailJob->status->value,
            errorMessage: $mailJob->error_message,
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'error_message' => $this->errorMessage,
        ];
    }
}
