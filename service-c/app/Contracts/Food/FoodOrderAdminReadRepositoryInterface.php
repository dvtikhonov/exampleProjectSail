<?php

declare(strict_types=1);

namespace App\Contracts\Food;

use App\Enums\Food\OrderReviewStatus;
use App\Models\FoodOrder;

/**
 * Чтение заказов еды для административного API проверки.
 */
interface FoodOrderAdminReadRepositoryInterface
{
    public function findById(int $id): ?FoodOrder;

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
     * Все заказы в хронологическом порядке (новые первыми).
     *
     * @return list<FoodOrder>
     */
    public function findAll(): array;
}
