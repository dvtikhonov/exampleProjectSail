<?php

namespace App\AbstractFilter\QueryFilters\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface Filter
{
    public function apply(Builder $query): Builder;
}

