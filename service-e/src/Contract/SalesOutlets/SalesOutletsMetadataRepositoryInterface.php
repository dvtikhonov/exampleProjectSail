<?php

declare(strict_types=1);

namespace App\Contract\SalesOutlets;

/** Контракт метаданных колонок таблицы торговых точек. */
interface SalesOutletsMetadataRepositoryInterface
{
    /**
     * @return array<int, array<string, bool|int|string|null>>
     */
    public function columns(): array;

    /**
     * @return array<int, string>
     */
    public function allowedColumnKeys(): array;
}
