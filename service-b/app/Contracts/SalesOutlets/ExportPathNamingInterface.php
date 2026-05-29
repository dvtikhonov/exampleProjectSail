<?php

namespace App\Contracts\SalesOutlets;

use App\Models\SalesOutletExportJob;

interface ExportPathNamingInterface
{
    public function forJob(SalesOutletExportJob $exportJob): string;

    public function downloadFileName(SalesOutletExportJob $exportJob): string;
}
