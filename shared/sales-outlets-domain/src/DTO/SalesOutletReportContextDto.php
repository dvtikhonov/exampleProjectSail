<?php

namespace Shared\SalesOutletsDomain\DTO;

readonly class SalesOutletReportContextDto
{
    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public SalesOutletFilterDto $filters,
        public array $columns = [],
        public array $params = [],
    ) {}

    /**
     * @param  array<int, string>  $columnKeys
     */
    public static function fromFilterValues(
        SalesOutletFilterDto $filters,
        array $columnKeys = [],
        array $params = [],
    ): self {
        return new self(
            filters: $filters,
            columns: $columnKeys,
            params: $params,
        );
    }
}
