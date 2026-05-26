<?php

namespace Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Filters;

use Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Contracts\Filter;
use Illuminate\Database\Eloquent\Builder;

final class WhereHasFilter implements Filter
{
    public function __construct(
        private readonly string $relation,
        private readonly Filter $filter,
        private readonly bool $enabled = true,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        if (!$this->enabled) {
            return $query;
        }

        return $query->whereHas($this->relation, function (Builder $subQuery): void {
            $this->filter->apply($subQuery);
        });
    }
}

