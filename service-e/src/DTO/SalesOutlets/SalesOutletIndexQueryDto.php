<?php

declare(strict_types=1);

namespace App\DTO\SalesOutlets;

/** Параметры запроса списка торговых точек (после валидации). */
readonly class SalesOutletIndexQueryDto
{
    /**
     * @param array<string, string> $columnFilters
     * @param array<int, string>    $columns
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
    ) {
    }

    /**
     * Собирает DTO из валидированных query-параметров с нормализацией пагинации и колонок.
     *
     * @param array<string, mixed> $validated
     * @param array<int, string>   $allowedColumns
     */
    public static function fromValidated(array $validated, array $allowedColumns): self
    {
        $requestedColumns = $validated['columns'] ?? null;
        $columns = is_array($requestedColumns)
            ? array_values(array_intersect(array_map('strval', $requestedColumns), $allowedColumns))
            : $allowedColumns;

        if ([] === $columns) {
            $columns = $allowedColumns;
        }

        return new self(
            search: trim((string) ($validated['search'] ?? '')),
            status: trim((string) ($validated['status'] ?? '')),
            columnFilters: self::columnFilters($validated['column_filters'] ?? [], $allowedColumns),
            sort: (string) ($validated['sort'] ?? 'id'),
            direction: ($validated['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
            page: max((int) ($validated['page'] ?? 1), 1),
            perPage: min(max((int) ($validated['per_page'] ?? 10), 5), 50),
            columns: $columns,
        );
    }

    /**
     * @param array<mixed>       $requestedFilters
     * @param array<int, string> $allowedColumns
     *
     * @return array<string, string>
     */
    private static function columnFilters(array $requestedFilters, array $allowedColumns): array
    {
        if (!is_array($requestedFilters)) {
            return [];
        }

        $filters = [];

        foreach ($allowedColumns as $column) {
            $value = trim((string) ($requestedFilters[$column] ?? ''));

            if ('' !== $value) {
                $filters[$column] = $value;
            }
        }

        return $filters;
    }
}
