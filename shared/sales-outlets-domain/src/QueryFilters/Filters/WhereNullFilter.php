<?php

namespace App\AbstractFilter\QueryFilters\Filters;

use App\AbstractFilter\QueryFilters\Contracts\Filter;
use Illuminate\Database\Eloquent\Builder;

final class WhereNullFilter implements Filter
{
    public function __construct(
        private readonly string $column,
        private readonly bool $enabled = true,
        private readonly bool $negated = false,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        if (!$this->enabled) {
            return $query;
        }

        return $this->negated
            ? $query->whereNotNull($this->column)
            : $query->whereNull($this->column);
    }
}

