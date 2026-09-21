<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\FoodOrderUpdateCommand;
use App\Enums\Food\Review\OrderReviewStep;

/**
 * Обработчик одного этапа проверки: формирует FoodOrderUpdateCommand для approve/reject.
 */
interface OrderReviewUpdateStepHandlerInterface
{
    /**
     * Этап проверки, за который отвечает handler.
     */
    public function step(): OrderReviewStep;

    /**
     * Команда обновления при одобрении этапа.
     */
    public function buildApprovalUpdate(
        FoodOrderRecord $order,
        int $adminId,
        string $reviewedAt,
    ): FoodOrderUpdateCommand;

    /**
     * Команда обновления при отклонении этапа.
     */
    public function buildRejectionUpdate(
        FoodOrderRecord $order,
        int $adminId,
        string $comment,
        string $reviewedAt,
    ): FoodOrderUpdateCommand;
}
