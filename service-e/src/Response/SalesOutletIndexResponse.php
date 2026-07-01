<?php

declare(strict_types=1);

namespace App\Response;

use App\Contract\SalesOutlets\SalesOutletTableMetaProviderInterface;
use App\Domain\SalesOutlet;
use App\DTO\SalesOutlets\SalesOutletIndexResultDto;
use App\Presentation\SalesOutletRowPresenter;

/** Формирует JSON-ответ списка торговых точек (data + meta). */
final class SalesOutletIndexResponse
{
    /**
     * Сериализует результат index в формат API (data + meta).
     *
     * @return array<string, mixed>
     */
    public static function from(
        SalesOutletIndexResultDto $result,
        SalesOutletTableMetaProviderInterface $tableMetaProvider,
    ): array {
        return [
            'data' => array_map(
                static fn (SalesOutlet $salesOutlet): array => SalesOutletRowPresenter::fromDomain($salesOutlet)->toArray(),
                $result->salesOutlets,
            ),
            'meta' => [
                'columns' => $tableMetaProvider->columns(),
                'filters' => [
                    'search' => $result->query->search,
                    'status' => $result->query->status,
                    'column_filters' => $result->query->columnFilters,
                    'sort' => $result->query->sort,
                    'direction' => $result->query->direction,
                    'page' => $result->pagination->currentPage,
                    'per_page' => $result->query->perPage,
                    'columns' => $result->query->columns,
                ],
                'pagination' => $result->pagination->toArray(),
                'status_options' => $tableMetaProvider->statusOptions(),
            ],
        ];
    }
}
