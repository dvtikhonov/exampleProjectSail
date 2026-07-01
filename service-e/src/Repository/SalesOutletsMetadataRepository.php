<?php

declare(strict_types=1);

namespace App\Repository;

use App\Contract\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use Shared\SalesOutletsDomain\Metadata\SalesOutletColumns;

/** Метаданные колонок таблицы из shared/sales-outlets-domain. */
class SalesOutletsMetadataRepository implements SalesOutletsMetadataRepositoryInterface
{
    /** {@inheritDoc} */
    public function columns(): array
    {
        return SalesOutletColumns::all();
    }

    /** {@inheritDoc} */
    public function allowedColumnKeys(): array
    {
        return SalesOutletColumns::keys();
    }
}
