<?php

namespace Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Filters;

use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Contracts\Filter;
use Illuminate\Database\Eloquent\Builder;

final class WhereBetweenFilter implements Filter
{
    public function __construct(
        private readonly string $column,
        private readonly mixed $from,
        private readonly mixed $to,
        private readonly bool $enabled = true,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        if (!$this->enabled) {
            return $query;
        }

        if ($this->from === null || $this->to === null) {
            return $query;
        }

        return $query->whereBetween($this->column, [$this->from, $this->to]);
    }
}

