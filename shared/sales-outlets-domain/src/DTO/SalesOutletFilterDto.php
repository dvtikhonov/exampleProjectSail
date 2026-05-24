<?php

namespace Shared\SalesOutletsDomain\DTO;

readonly class SalesOutletFilterDto
{
    /**
     * @param  array<string, string>  $columnFilters
     */
    public function __construct(
        public string $search,
        public string $status,
        public array $columnFilters,
        public string $sort,
        public string $direction,
    ) {}
}
