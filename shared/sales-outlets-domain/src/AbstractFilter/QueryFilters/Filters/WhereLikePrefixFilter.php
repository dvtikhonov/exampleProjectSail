<?php

namespace Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Filters;

use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Contracts\Filter;
use Illuminate\Database\Eloquent\Builder;

final class WhereLikePrefixFilter implements Filter
{
    public function __construct(
        private readonly string $column,
        private readonly ?string $prefix,
        private readonly bool $enabled = true,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        if (!$this->enabled) {
            return $query;
        }

        if ($this->prefix === null || $this->prefix === '') {
            return $query;
        }

        // Совместимость с текущим подходом: '%'/'%%' означают "не фильтровать"
        if ($this->prefix === '%' || $this->prefix === '%%') {
            return $query;
        }

        return $query->where($this->column, 'like', $this->prefix . '%');
    }
}

