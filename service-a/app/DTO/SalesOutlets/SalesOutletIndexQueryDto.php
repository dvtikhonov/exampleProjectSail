<?php

namespace App\DTO\SalesOutlets;

use Illuminate\Http\Request;

readonly class SalesOutletIndexQueryDto
{
    /**
     * @param  array<string, string>  $columnFilters
     * @param  array<int, string>  $columns
     */
    public function __construct(
        public string $search,
        public string $status,
        public array $columnFilters,
        public string $sort,
        public string $direction,
        public int $page,
        public int $perPage,
        public array $columns,
    ) {}

    /**
     * @param  array<int, string>  $allowedColumns
     */
    public static function fromRequest(Request $request, array $allowedColumns): self
    {
        $requestedColumns = $request->query('columns');
        $columns = is_array($requestedColumns)
            ? array_values(array_intersect(array_map('strval', $requestedColumns), $allowedColumns))
            : $allowedColumns;

        if ($columns === []) {
            $columns = $allowedColumns;
        }

        return new self(
            search: trim((string) $request->query('search', '')),
            status: trim((string) $request->query('status', '')),
            columnFilters: self::columnFilters($request, $allowedColumns),
            sort: (string) $request->query('sort', 'id'),
            direction: $request->query('direction') === 'desc' ? 'desc' : 'asc',
            page: max((int) $request->query('page', 1), 1),
            perPage: min(max((int) $request->query('per_page', 10), 5), 50),
            columns: $columns,
        );
    }

    /**
     * @param  array<int, string>  $allowedColumns
     * @return array<string, string>
     */
    private static function columnFilters(Request $request, array $allowedColumns): array
    {
        $requestedFilters = $request->query('column_filters', []);

        if (! is_array($requestedFilters)) {
            return [];
        }

        $filters = [];

        foreach ($allowedColumns as $column) {
            $value = trim((string) ($requestedFilters[$column] ?? ''));

            if ($value !== '') {
                $filters[$column] = $value;
            }
        }

        return $filters;
    }
}
