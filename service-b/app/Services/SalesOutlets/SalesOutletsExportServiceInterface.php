<?php

namespace App\Services\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use App\Models\SalesOutletExportJob;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface SalesOutletsExportServiceInterface
{
    /**
     * @return array<int, string>
     */
    public function allowedColumnKeys(): array;

    public function create(SalesOutletExportFilterDto $filters, ?int $userId): SalesOutletExportJob;

    public function findByUuid(string $uuid): ?SalesOutletExportJob;

    public function buildCsv(SalesOutletExportJob $exportJob): void;

    public function isDownloadReady(SalesOutletExportJob $exportJob): bool;

    public function download(SalesOutletExportJob $exportJob): StreamedResponse;
}
