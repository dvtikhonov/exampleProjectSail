<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\ExportFileStorageInterface;
use App\Contracts\SalesOutlets\ExportPathNamingInterface;
use App\Contracts\SalesOutlets\SalesOutletsCsvWriterInterface;
use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use App\Enums\SalesOutletExportStatus;
use App\Jobs\BuildSalesOutletsCsvExportJob;
use App\Models\SalesOutletExportJob;
use App\Repositories\SalesOutlets\SalesOutletsExportMetadataRepositoryInterface;
use App\Repositories\SalesOutlets\SalesOutletsExportRepositoryInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SalesOutletsExportService implements SalesOutletsExportApiServiceInterface, SalesOutletsExportWorkerServiceInterface
{
    public function __construct(
        private readonly SalesOutletsExportRepositoryInterface $exportRepository,
        private readonly SalesOutletsExportMetadataRepositoryInterface $metadataRepository,
        private readonly ExportFileStorageInterface $fileStorage,
        private readonly SalesOutletsCsvWriterInterface $csvWriter,
        private readonly ExportPathNamingInterface $pathNaming,
    ) {}

    public function create(SalesOutletExportFilterDto $filters, ?int $userId): SalesOutletExportJob
    {
        $exportJob = $this->exportRepository->create($filters, $userId);

        dispatch(new BuildSalesOutletsCsvExportJob(uuid: $exportJob->uuid));

        return $exportJob;
    }

    public function findByUuid(string $uuid): ?SalesOutletExportJob
    {
        return $this->exportRepository->findByUuid($uuid);
    }

    public function buildByUuid(string $uuid): void
    {
        $exportJob = $this->exportRepository->findByUuid($uuid);

        if ($exportJob === null) {
            return;
        }

        $this->buildCsv($exportJob);
    }

    public function markAsFailed(string $uuid, ?string $errorMessage = null): void
    {
        $exportJob = $this->exportRepository->findByUuid($uuid);

        if ($exportJob === null || $exportJob->status === SalesOutletExportStatus::Failed) {
            return;
        }

        $this->exportRepository->updateStatus(
            $exportJob,
            SalesOutletExportStatus::Failed,
            errorMessage: $errorMessage ?? 'Export job failed.',
        );
    }

    public function isDownloadReady(SalesOutletExportJob $exportJob): bool
    {
        return $exportJob->status === SalesOutletExportStatus::Completed
            && $exportJob->file_path !== null
            && $this->fileStorage->exists($exportJob->file_path);
    }

    public function download(SalesOutletExportJob $exportJob): StreamedResponse
    {
        if (! $this->isDownloadReady($exportJob)) {
            throw new RuntimeException('Export file is not ready.');
        }

        return $this->fileStorage->download(
            $exportJob->file_path,
            $this->pathNaming->downloadFileName($exportJob),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function buildCsv(SalesOutletExportJob $exportJob): void
    {
        $this->exportRepository->updateStatus($exportJob, SalesOutletExportStatus::Processing);

        try {
            $delaySeconds = max((int) config('sales_outlets_export.fake_delay_seconds'), 0);

            if ($delaySeconds > 0) {
                sleep($delaySeconds);
            }

            $filters = SalesOutletExportFilterDto::fromValidated(
                validated: $exportJob->filters,
                allowedColumns: $this->metadataRepository->allowedColumnKeys(),
            );
            $filePath = $this->pathNaming->forJob($exportJob);

            $this->fileStorage->put($filePath, $this->csvWriter->build($filters));

            $this->exportRepository->updateStatus($exportJob, SalesOutletExportStatus::Completed, $filePath);
        } catch (Throwable $exception) {
            $this->exportRepository->updateStatus(
                $exportJob,
                SalesOutletExportStatus::Failed,
                errorMessage: $exception->getMessage(),
            );

            throw $exception;
        }
    }
}
