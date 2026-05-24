<?php

namespace App\Repositories\SalesOutlets;

use Shared\SalesOutletsDomain\Metadata\SalesOutletColumns;

class SalesOutletsExportMetadataRepository implements SalesOutletsExportMetadataRepositoryInterface
{
    public function columns(): array
    {
        return SalesOutletColumns::all();
    }

    public function allowedColumnKeys(): array
    {
        return SalesOutletColumns::keys();
    }
}
