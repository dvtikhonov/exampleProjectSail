<?php

namespace App\Contracts\Max;

use App\DTO\Max\MaxReportConfig;

interface MaxReportConfigProviderInterface
{
    public function config(): MaxReportConfig;
}
