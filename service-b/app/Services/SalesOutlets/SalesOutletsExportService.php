<?php

namespace App\Services\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use App\Enums\SalesOutletExportStatus;
use App\Jobs\BuildSalesOutletsCsvExportJob;
use App\Models\SalesOutletExportJob;
use App\Repositories\SalesOutlets\SalesOutletsDataRepositoryInterface;
use App\Repositories\SalesOutlets\SalesOutletsExportMetadataRepositoryInterface;
use App\Repositories\SalesOutlets\SalesOutletsExportRepositoryInterface;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SalesOutletsExportService implements SalesOutletsExportServiceInterface
{
    public function __construct(
        private readonly SalesOutletsExportRepositoryInterface $exportRepository,
        private readonly SalesOutletsDataRepositoryInterface $dataRepository,
        private readonly SalesOutletsExportMetadataRepositoryInterface $metadataRepository,
    ) {}

    public function allowedColumnKeys(): array
    {
        return $this->metadataRepository->allowedColumnKeys();
    }

    public function create(SalesOutletExportFilterDto $filters, ?int $userId): SalesOutletExportJob
    {
        $exportJob = $this->exportRepository->create($filters, $userId);

        BuildSalesOutletsCsvExportJob::dispatch($exportJob->uuid);

        return $exportJob;
    }

    public function findByUuid(string $uuid): ?SalesOutletExportJob
    {
        return $this->exportRepository->findByUuid($uuid);
    }

    public function buildCsv(SalesOutletExportJob $exportJob): void
    {
        $this->exportRepository->updateStatus($exportJob, SalesOutletExportStatus::Processing);

        try {
            $delaySeconds = max((int) config('sales_outlets_export.fake_delay_seconds'), 0);

            if ($delaySeconds > 0) {
                sleep($delaySeconds);
            }

            $filters = SalesOutletExportFilterDto::fromValidated(
                validated: $exportJob->filters,
                allowedColumns: $this->allowedColumnKeys(),
            );
            $filePath = 'exports/sales-outlets-'.$exportJob->uuid.'.csv';

            Storage::disk('local')->put($filePath, $this->csv($filters));

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

    public function isDownloadReady(SalesOutletExportJob $exportJob): bool
    {
        return $exportJob->status === SalesOutletExportStatus::Completed
            && $exportJob->file_path !== null
            && Storage::disk('local')->exists($exportJob->file_path);
    }

    public function download(SalesOutletExportJob $exportJob): StreamedResponse
    {
        if (! $this->isDownloadReady($exportJob)) {
            throw new RuntimeException('Export file is not ready.');
        }

        return Storage::disk('local')->download(
            $exportJob->file_path,
            $this->downloadFileName($exportJob),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function downloadFileName(SalesOutletExportJob $exportJob): string
    {
        if ($exportJob->user_id === null) {
            return 'objects-sales-outlets.csv';
        }

        return 'objects-sales-outlets-'.$exportJob->user_id.'.csv';
    }

    private function csv(SalesOutletExportFilterDto $filters): string
    {
        $columns = array_values(array_filter(
            $this->metadataRepository->columns(),
            fn (array $column): bool => in_array($column['key'], $filters->columns, true),
        ));

        $rows = [$this->csvLine(array_column($columns, 'label'))];

        foreach ($this->dataRepository->exportRows($filters) as $row) {
            $rows[] = $this->csvLine(array_map(
                fn (array $column): string => (string) ($row[$column['key']] ?? ''),
                $columns,
            ));
        }

        return "\xEF\xBB\xBF".implode("\n", $rows);
    }

    /**
     * @param  array<int, string>  $values
     */
    private function csvLine(array $values): string
    {
        return implode(';', array_map(
            fn (string $value): string => '"'.str_replace('"', '""', $value).'"',
            $values,
        ));
    }
}
