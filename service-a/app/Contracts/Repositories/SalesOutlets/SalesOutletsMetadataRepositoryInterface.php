<?php

namespace App\Contracts\Repositories\SalesOutlets;

interface SalesOutletsMetadataRepositoryInterface
{
    /**
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     sortable: bool,
     *     searchable?: bool,
     *     filterType?: string|null,
     *     sortColumn?: string
     * }>
     */
    public function columns(): array;

    /**
     * @return array<int, string>
     */
    public function allowedColumnKeys(): array;
}
