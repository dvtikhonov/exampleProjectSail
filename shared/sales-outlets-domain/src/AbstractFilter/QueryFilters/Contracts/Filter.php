<?php

namespace Shared\SalesOutletsDomain\AbstractFilter\QueryFilters\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface Filter
{
    public function apply(Builder $query): Builder;
}

