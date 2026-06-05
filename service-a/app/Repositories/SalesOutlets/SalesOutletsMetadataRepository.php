<?php

namespace App\Repositories\SalesOutlets;

use App\Contracts\Repositories\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use Shared\SalesOutletsDomain\Metadata\SalesOutletColumns;

class SalesOutletsMetadataRepository implements SalesOutletsMetadataRepositoryInterface
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
