<?php

namespace App\DTO\ObjectsSalesOutlets;

readonly class SalesOutletsPageDto
{
    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<int, array<string, mixed>>  $salesOutlets
     * @param  array<string, mixed>  $filters
     * @param  array<string, int>  $pagination
     * @param  array<int, array<string, string>>  $statusOptions
     */
    public function __construct(
        public array $columns,
        public array $salesOutlets,
        public array $filters,
        public array $pagination,
        public array $statusOptions,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromServicePayload(array $payload): self
    {
        $meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];

        return new self(
            columns: self::arrayValue($meta, 'columns'),
            salesOutlets: self::arrayValue($payload, 'data'),
            filters: self::arrayValue($meta, 'filters'),
            pagination: self::arrayValue($meta, 'pagination'),
            statusOptions: self::arrayValue($meta, 'status_options'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toInertiaProps(string $routeName): array
    {
        return [
            'columns' => $this->columns,
            'salesOutlets' => $this->salesOutlets,
            'filters' => $this->filters,
            'pagination' => $this->pagination,
            'statusOptions' => $this->statusOptions,
            'routes' => [
                'index' => route($routeName),
                'exportCreate' => route('objectsSalesOutlets.export.create'),
                'exportStatus' => route('objectsSalesOutlets.export.status', ['uuid' => '__uuid__']),
                'exportDownload' => route('objectsSalesOutlets.export.download', ['uuid' => '__uuid__']),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<mixed>
     */
    private static function arrayValue(array $source, string $key): array
    {
        return is_array($source[$key] ?? null) ? $source[$key] : [];
    }
}
