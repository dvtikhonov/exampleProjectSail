<?php

namespace App\Contracts\Repositories\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletReportStatsDto;

interface SalesOutletsReportStatsRepositoryInterface
{
    public function aggregate(): SalesOutletReportStatsDto;
}
