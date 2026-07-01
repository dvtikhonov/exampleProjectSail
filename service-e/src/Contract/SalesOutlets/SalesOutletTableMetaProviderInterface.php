<?php

declare(strict_types=1);

namespace App\Contract\SalesOutlets;

/** Контракт метаданных UI таблицы (колонки и опции статусов). */
interface SalesOutletTableMetaProviderInterface
{
    /**
     * Колонки таблицы с UI-настройками (width, visible и т.д.).
     *
     * @return array<int, array<string, bool|int|string>>
     */
    public function columns(): array;

    /**
     * Опции фильтра по статусу, включая пункт «Все».
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function statusOptions(): array;
}
