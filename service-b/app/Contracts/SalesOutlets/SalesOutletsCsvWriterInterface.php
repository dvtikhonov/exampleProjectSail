<?php

namespace App\Contracts\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletExportFilterDto;

interface SalesOutletsCsvWriterInterface
{
    public function build(SalesOutletExportFilterDto $filters): string;
}
