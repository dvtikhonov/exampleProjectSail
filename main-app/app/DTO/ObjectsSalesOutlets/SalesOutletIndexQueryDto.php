<?php

namespace App\DTO\ObjectsSalesOutlets;

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

    public static function fromRequest(Request $request): self
    {
        $requestedColumns = $request->query('columns', []);

        return new self(
            search: (string) $request->query('search', ''),
            status: (string) $request->query('status', ''),
            columnFilters: self::columnFilters($request),
            sort: (string) $request->query('sort', 'id'),
            direction: $request->query('direction') === 'desc' ? 'desc' : 'asc',
            page: max((int) $request->query('page', 1), 1),
            perPage: min(max((int) $request->query('per_page', 10), 5), 50),
            columns: is_array($requestedColumns) ? array_values(array_map('strval', $requestedColumns)) : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status,
            'column_filters' => $this->columnFilters,
            'sort' => $this->sort,
            'direction' => $this->direction,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'columns' => $this->columns,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function columnFilters(Request $request): array
    {
        $requestedFilters = $request->query('column_filters', []);

        if (! is_array($requestedFilters)) {
            return [];
        }

        $filters = [];

        foreach ($requestedFilters as $key => $value) {
            $filterValue = trim((string) $value);

            if ($filterValue !== '') {
                $filters[(string) $key] = $filterValue;
            }
        }

        return $filters;
    }
}
