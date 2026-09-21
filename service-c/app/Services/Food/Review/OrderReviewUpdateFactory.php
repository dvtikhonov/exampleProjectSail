<?php

declare(strict_types=1);

namespace App\Services\Food\Review;

use App\Contracts\Food\Review\OrderReviewUpdateStepHandlerInterface;
use App\Contracts\Shared\ClockInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\FoodOrderUpdateCommand;
use App\Enums\Food\Review\OrderReviewStep;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Формирует команду обновления для approve/reject этапа проверки заказа.
 *
 * Внутренний collaborator Review; не инжектить из Delivery.
 * Делегирует в реестр {@see OrderReviewUpdateStepHandlerInterface} (один handler = один шаг).
 */
class OrderReviewUpdateFactory
{
    /**
     * @param  array<string, OrderReviewUpdateStepHandlerInterface>  $handlers
     */
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly array $handlers,
    ) {}

    /**
     * Собирает команду обновления при одобрении шага проверки.
     */
    public function buildApprovalUpdate(
        OrderReviewStep $step,
        FoodOrderRecord $order,
        int $adminId,
    ): FoodOrderUpdateCommand {
        $reviewedAt = $this->clock->now()->format(DateTimeInterface::ATOM);

        return $this->handlerFor($step)->buildApprovalUpdate($order, $adminId, $reviewedAt);
    }

    /**
     * Собирает команду обновления при отклонении шага проверки.
     */
    public function buildRejectionUpdate(
        OrderReviewStep $step,
        FoodOrderRecord $order,
        int $adminId,
        string $comment,
    ): FoodOrderUpdateCommand {
        $reviewedAt = $this->clock->now()->format(DateTimeInterface::ATOM);

        return $this->handlerFor($step)->buildRejectionUpdate($order, $adminId, $comment, $reviewedAt);
    }

    /**
     * Возвращает handler для этапа или бросает исключение, если реестр неполный.
     *
     * @throws InvalidArgumentException
     */
    private function handlerFor(OrderReviewStep $step): OrderReviewUpdateStepHandlerInterface
    {
        $handler = $this->handlers[$step->value] ?? null;

        if (! $handler instanceof OrderReviewUpdateStepHandlerInterface) {
            throw new InvalidArgumentException(
                sprintf('No OrderReviewUpdateStepHandler registered for step: %s', $step->value),
            );
        }

        return $handler;
    }
}
