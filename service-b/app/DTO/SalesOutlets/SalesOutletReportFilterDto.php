<?php

namespace App\DTO\SalesOutlets;

readonly class SalesOutletReportFilterDto
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
        public array $columns,
    ) {}

    /**
     * @param  array<string, mixed>  $stored
     * @param  array<int, string>  $allowedColumnKeys
     */
    public static function fromStoredArray(array $stored, array $allowedColumnKeys): self
    {
        return self::fromValidated($stored, $allowedColumnKeys);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<int, string>  $allowedColumns
     */
    public static function fromValidated(array $validated, array $allowedColumns): self
    {
        $columns = array_values(array_intersect(
            array_map('strval', $validated['columns'] ?? []),
            $allowedColumns,
        ));

        if ($columns === []) {
            $columns = $allowedColumns;
        }

        return new self(
            search: trim((string) ($validated['search'] ?? '')),
            status: trim((string) ($validated['status'] ?? '')),
            columnFilters: self::columnFilters($validated['column_filters'] ?? [], $allowedColumns),
            sort: (string) ($validated['sort'] ?? 'id'),
            direction: ($validated['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
            columns: $columns,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->status,
            'column_filters' => $this->columnFilters,
            'sort' => $this->sort,
            'direction' => $this->direction,
            'columns' => $this->columns,
        ];
    }

    /**
     * @param  array<mixed>  $requestedFilters
     * @param  array<int, string>  $allowedColumns
     * @return array<string, string>
     */
    private static function columnFilters(array $requestedFilters, array $allowedColumns): array
    {
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
