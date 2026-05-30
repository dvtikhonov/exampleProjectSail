<?php

namespace App\Domain\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use App\Models\SalesOutletReportJob;

readonly class SalesOutletAsyncJob
{
    public function __construct(
        public string $uuid,
        public ?int $userId,
        public AsyncJobStatus $status,
        public SalesOutletsReportType $reportType,
        public SalesOutletReportFilterDto $filters,
        public ?string $filePath = null,
        public ?string $errorMessage = null,
    ) {}

    public static function fromReportJob(
        SalesOutletReportJob $reportJob,
        SalesOutletReportFilterDto $filters,
    ): self {
        return new self(
            uuid: $reportJob->uuid,
            userId: $reportJob->user_id,
            status: $reportJob->status,
            reportType: $reportJob->report_type,
            filters: $filters,
            filePath: $reportJob->file_path,
            errorMessage: $reportJob->error_message,
        );
    }
}
