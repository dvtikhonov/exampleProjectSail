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
    public function toInertiaProps(string $routeName, bool $includeReportRoutes = true): array
    {
        $routes = [
            'index' => route($routeName),
        ];

        if ($includeReportRoutes) {
            $routes = [
                ...$routes,
                'exportCreate' => route('objectsSalesOutlets.export.create'),
                'exportStatus' => route('objectsSalesOutlets.export.status', ['uuid' => '__uuid__']),
                'exportDownload' => route('objectsSalesOutlets.export.download', ['uuid' => '__uuid__']),
                'mailCreate' => route('objectsSalesOutlets.mail.create'),
                'mailStatus' => route('objectsSalesOutlets.mail.status', ['uuid' => '__uuid__']),
                'maxCreate' => route('objectsSalesOutlets.max.create'),
                'maxStatus' => route('objectsSalesOutlets.max.status', ['uuid' => '__uuid__']),
                'reportStats' => route('objectsSalesOutlets.reports.stats'),
            ];
        }

        return [
            'columns' => $this->columns,
            'salesOutlets' => $this->salesOutlets,
            'filters' => $this->filters,
            'pagination' => $this->pagination,
            'statusOptions' => $this->statusOptions,
            'routes' => $routes,
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
