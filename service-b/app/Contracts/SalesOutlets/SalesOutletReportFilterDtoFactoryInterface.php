<?php

namespace App\Contracts\SalesOutlets;

use App\DTO\SalesOutlets\SalesOutletReportFilterDto;

interface SalesOutletReportFilterDtoFactoryInterface
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function fromValidated(array $validated): SalesOutletReportFilterDto;
}
