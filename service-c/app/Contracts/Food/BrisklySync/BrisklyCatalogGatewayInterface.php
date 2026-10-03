<?php

declare(strict_types=1);

namespace App\Contracts\Food\BrisklySync;

use App\DTO\Food\BrisklySync\BrisklyCategoryDto;
use App\DTO\Food\BrisklySync\BrisklyCreatedItemDto;
use App\DTO\Food\BrisklySync\BrisklySnapshotItemDto;
use App\Exceptions\Food\FoodDomainException;

/**
 * Порт к Briskly company API (snapshot / categories / update / create).
 */
interface BrisklyCatalogGatewayInterface
{
    /**
     * Snapshot каталога без category filter; searchText — client-side после fetch.
     *
     * @return list<BrisklySnapshotItemDto>
     *
     * @throws FoodDomainException
     */
    public function fetchSnapshot(string $token, ?string $searchText): array;

    /**
     * Категории для CREATE UI.
     *
     * @return list<BrisklyCategoryDto>
     *
     * @throws FoodDomainException
     */
    public function listCategories(string $token): array;

    /**
     * UPDATE цены: get-by-id → merge price → update.
     *
     * @throws FoodDomainException
     */
    public function updateItemPrice(string $token, int $itemId, string $price): void;

    /**
     * CREATE позиции в Briskly.
     *
     * @throws FoodDomainException
     */
    public function createItem(
        string $token,
        string $name,
        string $price,
        int $categoryId,
        int $catalogId,
    ): BrisklyCreatedItemDto;
}
