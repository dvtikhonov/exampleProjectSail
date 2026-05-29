<?php

namespace App\Services\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use App\Models\SalesOutletExportJob;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface SalesOutletsExportApiServiceInterface
{
    public function create(SalesOutletExportFilterDto $filters, ?int $userId): SalesOutletExportJob;

    public function findByUuid(string $uuid): ?SalesOutletExportJob;

    public function isDownloadReady(SalesOutletExportJob $exportJob): bool;

    public function download(SalesOutletExportJob $exportJob): StreamedResponse;
}
