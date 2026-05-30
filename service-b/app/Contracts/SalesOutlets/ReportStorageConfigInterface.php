<?php

namespace App\Contracts\SalesOutlets;

interface ReportStorageConfigInterface
{
    public function storageDisk(): string;
}
