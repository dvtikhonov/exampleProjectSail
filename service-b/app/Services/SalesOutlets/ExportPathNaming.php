<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\ExportPathNamingInterface;
use App\Models\SalesOutletExportJob;

class ExportPathNaming implements ExportPathNamingInterface
{
    public function forJob(SalesOutletExportJob $exportJob): string
    {
        return 'exports/sales-outlets-'.$exportJob->uuid.'.csv';
    }

    public function downloadFileName(SalesOutletExportJob $exportJob): string
    {
        if ($exportJob->user_id === null) {
            return 'objects-sales-outlets.csv';
        }

        return 'objects-sales-outlets-'.$exportJob->user_id.'.csv';
    }
}
