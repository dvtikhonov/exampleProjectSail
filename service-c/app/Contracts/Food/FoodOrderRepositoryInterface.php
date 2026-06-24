<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Enums\Food\OrderReviewStatus;
use App\Models\FoodOrder;

/**
 * Репозиторий заказов еды MAX mini-app.
 */
interface FoodOrderRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): FoodOrder;

    public function findById(int $id): ?FoodOrder;

    public function findByIdForUpdate(int $id): ?FoodOrder;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(FoodOrder $order, array $attributes): FoodOrder;

    /**
     * Заказы для проверки адреса с указанным статусом этапа.
     *
     * @return list<FoodOrder>
     */
    public function findForAddressReview(OrderReviewStatus $reviewStatus): array;

    /**
     * Заказы для проверки состава с указанным статусом этапа.
     *
     * @return list<FoodOrder>
     */
    public function findForCompositionReview(OrderReviewStatus $reviewStatus): array;

    /**
     * Заказы клиента в хронологическом порядке (новые первыми).
     *
     * @return list<FoodOrder>
     */
    public function findByMaxUserId(int $maxUserId): array;

    /**
     * Все заказы в хронологическом порядке (новые первыми).
     *
     * @return list<FoodOrder>
     */
    public function findAll(): array;
}
