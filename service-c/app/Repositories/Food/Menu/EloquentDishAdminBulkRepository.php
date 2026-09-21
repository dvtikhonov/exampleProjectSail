<?php

declare(strict_types=1);

namespace App\Repositories\Food\Menu;

use App\Contracts\Food\Menu\DishAdminBulkRepositoryInterface;
use App\Models\Food\Dish;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-реализация bulk-порта административного репозитория блюд.
 */
class EloquentDishAdminBulkRepository implements DishAdminBulkRepositoryInterface
{
    public function __construct(
        private readonly DishMapper $dishMapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function updatePricesByIds(array $pricesById): void
    {
        if ($pricesById === []) {
            return;
        }

        $cases = [];
        $bindings = [];

        foreach ($pricesById as $id => $price) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = (int) $id;
            $bindings[] = $price;
        }

        $ids = array_map(static fn ($id): int => (int) $id, array_keys($pricesById));
        $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
        $caseSql = implode(' ', $cases);

        DB::update(
            "UPDATE max_dishes SET price = CASE id {$caseSql} END, updated_at = ? WHERE id IN ({$idPlaceholders}) AND deleted_at IS NULL",
            [...$bindings, now(), ...$ids],
        );
    }

    /**
     * {@inheritDoc}
     */
    public function createMany(array $dtos): array
    {
        $created = [];

        foreach ($dtos as $dto) {
            $created[] = $this->dishMapper->toRecord(
                Dish::query()->create($this->dishMapper->toCreateAttributes($dto)),
            );
        }

        return $created;
    }

    /**
     * {@inheritDoc}
     */
    public function updateImageUrlsByIds(array $imageUrlsById): void
    {
        if ($imageUrlsById === []) {
            return;
        }

        $cases = [];
        $bindings = [];

        foreach ($imageUrlsById as $id => $imageUrl) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = (int) $id;
            $bindings[] = $imageUrl;
        }

        $ids = array_map(static fn ($id): int => (int) $id, array_keys($imageUrlsById));
        $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
        $caseSql = implode(' ', $cases);

        DB::update(
            "UPDATE max_dishes SET image_url = CASE id {$caseSql} END, updated_at = ? WHERE id IN ({$idPlaceholders}) AND deleted_at IS NULL",
            [...$bindings, now(), ...$ids],
        );
    }
}
