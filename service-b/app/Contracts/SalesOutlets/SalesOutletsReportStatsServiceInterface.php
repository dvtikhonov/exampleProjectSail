<?php

namespace App\Contracts\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletReportStatsDto;

interface SalesOutletsReportStatsServiceInterface
{
    public function aggregate(): SalesOutletReportStatsDto;
}
