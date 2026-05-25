<?php

namespace App\AbstractFilter\QueryFilters\Composite;

use App\AbstractFilter\QueryFilters\Contracts\Filter;
use Illuminate\Database\Eloquent\Builder;

final class CompositeFilter implements Filter
{
    /**
     * @param Filter[] $filters
     */
    public function __construct(private readonly array $filters)
    {
    }

    public function apply(Builder $query): Builder
    {
        foreach ($this->filters as $filter) {
            $query = $filter->apply($query);
        }

        return $query;
    }
}

