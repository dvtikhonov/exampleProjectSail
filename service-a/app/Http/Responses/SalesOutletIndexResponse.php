<?php

namespace App\Http\Responses;

use App\Contracts\SalesOutlets\SalesOutletTableMetaProviderInterface;
use App\DTO\SalesOutlets\SalesOutletIndexResultDto;
use App\Domain\SalesOutlets\SalesOutlet;
use App\Presentation\SalesOutlets\SalesOutletRowPresenter;

final class SalesOutletIndexResponse
{
    /**
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
