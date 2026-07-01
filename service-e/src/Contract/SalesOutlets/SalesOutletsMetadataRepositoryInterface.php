<?php

declare(strict_types=1);

namespace App\Contract\SalesOutlets;

/** Контракт метаданных колонок таблицы торговых точек. */
interface SalesOutletsMetadataRepositoryInterface
{
    /**
     * Описание колонок таблицы (key, label, sortable и т.д.).
     *
     * @return array<int, array<string, bool|int|string|null>>
     */
    public function columns(): array;

    /**
     * Ключи колонок, доступных для sort/columns query-параметров.
     *
     * @return array<int, string>
     */
    public function allowedColumnKeys(): array;
}
