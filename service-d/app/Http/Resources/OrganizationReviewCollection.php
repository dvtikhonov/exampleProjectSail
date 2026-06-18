<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Пагинированная коллекция отзывов с meta (current_page, last_page и т.д.).
 */
class OrganizationReviewCollection extends ResourceCollection
{
    /** @var class-string<OrganizationReviewResource> */
    public $collects = OrganizationReviewResource::class;

    /**
     * Оборачивает страницу отзывов в data + meta для JSON-ответа.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator<int, mixed> $paginator */
        $paginator = $this->resource;

        return [
            'data' => $this->collection->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
