<?php

namespace App\AbstractFilter\QueryFilters\Groups;

use App\AbstractFilter\QueryFilters\Contracts\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Скобочная OR-группа: (alt1 OR alt2 OR alt3), где каждый alt — это Filter,
 * обычно CompositeFilter/WhereGroup с набором условий.
 */
final class OrGroup implements Filter
{
    /**
     * @param Filter[] $alternatives
     */
    public function __construct(
        private readonly array $alternatives,
        private readonly bool $enabled = true,
    ) {
    }

    public function apply(Builder $query): Builder
    {
        if (!$this->enabled) {
            return $query;
        }

        if ($this->alternatives === []) {
            return $query;
        }

        return $query->where(function (Builder $subQuery): void {
            $first = true;

            foreach ($this->alternatives as $alternative) {
                if ($first) {
                    $subQuery->where(function (Builder $altQuery) use ($alternative): void {
                        $alternative->apply($altQuery);
                    });
                    $first = false;
                    continue;
                }

                $subQuery->orWhere(function (Builder $altQuery) use ($alternative): void {
                    $alternative->apply($altQuery);
                });
            }
        });
    }
}

