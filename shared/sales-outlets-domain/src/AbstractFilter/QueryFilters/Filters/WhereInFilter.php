<?php

namespace Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Filters;

use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Contracts\Filter;
use Illuminate\Database\Eloquent\Builder;

final class WhereInFilter implements Filter
{
    /**
     * @param array<int, mixed>|null $values
     */
    public function __construct(
        private readonly string $column,
        private readonly ?array $values,
        private readonly bool $enabled = true,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        if (!$this->enabled) {
            return $query;
        }

        if (!$this->values || count($this->values) === 0) {
            return $query;
        }

        return $query->whereIn($this->column, $this->values);
    }
}

