<?php

namespace App\Contracts\SalesOutlets;

interface HtmlTableRendererInterface
{
    /**
     * @param  array<int, array{key: string, label: string}>  $columns
     * @param  iterable<int, array<string, int|string|null>>  $rows
     */
    public function render(array $columns, iterable $rows): string;
}
