<?php

namespace App\Repositories\SalesOutlets;

interface SalesOutletsExportMetadataRepositoryInterface
{
    /**
     * @return array<int, array<string, bool|int|string>>
     */
    public function columns(): array;

    /**
     * @return array<int, string>
     */
    public function allowedColumnKeys(): array;
}
