<?php

namespace App\AbstractFilter\QueryFilters\Groups;

use App\AbstractFilter\QueryFilters\Contracts\Filter;
use Illuminate\Database\Eloquent\Builder;

final class WhereGroup implements Filter
{
    /**
     * @param Filter[] $filters
     */
    public function __construct(
        private readonly array $filters,
        private readonly bool $enabled = true,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        if (!$this->enabled) {
            return $query;
        }

        return $query->where(function (Builder $subQuery): void {
            foreach ($this->filters as $filter) {
                $filter->apply($subQuery);
            }
        });
    }
}

