<?php

namespace App\AbstractFilter\QueryFilters\Filters;

use App\AbstractFilter\QueryFilters\Contracts\Filter;
use Illuminate\Database\Eloquent\Builder;

final class WhereFilter implements Filter
{
    public function __construct(
        private readonly string $column,
        private readonly string $operator,
        private readonly mixed $value,
        private readonly bool $enabled = true,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        if (!$this->enabled) {
            return $query;
        }

        if ($this->value === null || $this->value === '') {
            return $query;
        }

        return $query->where($this->column, $this->operator, $this->value);
    }
}

