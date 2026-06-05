<?php

namespace App\DTO\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutlet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

readonly class SalesOutletIndexResultDto
{
    /**
     * @param  array<int, SalesOutlet>  $salesOutlets
     */
    public function __construct(
        public array $salesOutlets,
        public SalesOutletPaginationDto $pagination,
        public SalesOutletIndexQueryDto $query,
    ) {}

    public static function fromPaginator(
        LengthAwarePaginator $paginator,
        SalesOutletIndexQueryDto $query,
    ): self {
        /** @var array<int, SalesOutlet> $salesOutlets */
        $salesOutlets = $paginator->getCollection()
            ->values()
            ->all();

        return new self(
            salesOutlets: $salesOutlets,
            pagination: SalesOutletPaginationDto::fromPaginator($paginator),
            query: $query,
        );
    }
}
