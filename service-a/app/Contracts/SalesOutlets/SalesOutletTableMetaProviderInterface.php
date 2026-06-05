<?php

namespace App\Contracts\SalesOutlets;

interface SalesOutletTableMetaProviderInterface
{
    /**
     * @return array<int, array<string, bool|int|string>>
     */
    public function columns(): array;

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function statusOptions(): array;
}
