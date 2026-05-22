<?php

namespace App\DTO\SalesOutlets;

use App\Models\SalesOutletExportJob;

readonly class SalesOutletExportJobDto
{
    public function __construct(
        public string $uuid,
        public string $status,
        public ?string $errorMessage,
    ) {}

    public static function fromModel(SalesOutletExportJob $exportJob): self
    {
        return new self(
            uuid: $exportJob->uuid,
            status: $exportJob->status->value,
            errorMessage: $exportJob->error_message,
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
