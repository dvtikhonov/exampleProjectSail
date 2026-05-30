<?php

namespace App\DTO\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutletAsyncJob;

readonly class SalesOutletReportJobDto
{
    public function __construct(
        public string $uuid,
        public string $status,
        public string $reportType,
        public ?string $errorMessage,
    ) {}

    public static function fromAsyncJob(SalesOutletAsyncJob $reportJob): self
    {
        return new self(
            uuid: $reportJob->uuid,
            status: $reportJob->status->value,
            reportType: $reportJob->reportType->value,
            errorMessage: $reportJob->errorMessage,
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
            'report_type' => $this->reportType,
            'error_message' => $this->errorMessage,
        ];
    }
}
