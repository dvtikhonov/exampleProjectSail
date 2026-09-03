<?php

declare(strict_types=1);

namespace App\Services\Food\Composition;

use App\Contracts\Food\Composition\OrderCompositionSnapshotBuilderInterface;
use App\Contracts\Food\Composition\OrderCompositionUpdateServiceInterface;
use App\Contracts\Food\Order\FoodOrderWriteRepositoryInterface;
use App\Contracts\Food\Review\FoodOrderCustomerNotifierInterface;
use App\Contracts\Shared\TransactionManagerInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\FoodOrderUpdateCommand;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Exceptions\Food\FoodDomainException;
use App\Services\Food\Review\OrderReviewAuthorizationService;

/**
 * Обновление состава заказа проверяющим composition_reviewer.
 */
class OrderCompositionUpdateService implements OrderCompositionUpdateServiceInterface
{
    public function __construct(
        private readonly FoodOrderWriteRepositoryInterface $foodOrderWriteRepository,
        private readonly OrderReviewAuthorizationService $orderReviewAuthorizationService,
        private readonly OrderCompositionSnapshotBuilderInterface $orderCompositionSnapshotBuilder,
        private readonly FoodOrderCustomerNotifierInterface $foodOrderCustomerNotifier,
        private readonly TransactionManagerInterface $transactionManager,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function update(int $orderId, MaxUserIdentity $admin, array $items): FoodOrderRecord
    {
        $order = $this->transactionManager->run(function () use ($orderId, $admin, $items): FoodOrderRecord {
            $order = $this->foodOrderWriteRepository->findByIdForUpdate($orderId);

            if ($order === null) {
                throw new FoodDomainException('Заказ не найден.', 404);
            }

            $this->orderReviewAuthorizationService->assertCanEditComposition($admin, $order);

            $composition = $this->orderCompositionSnapshotBuilder->build(
                restaurantId: $order->restaurantId,
                customerMaxUserId: $order->maxUserId,
                items: $items,
                existingDishIds: $this->existingDishIdsFromSnapshot($order->itemsSnapshot),
            );

            return $this->foodOrderWriteRepository->update($order, new FoodOrderUpdateCommand(
                itemsSnapshot: $composition->itemsSnapshot,
                itemsTotal: $composition->itemsTotal,
                deliveryCost: $composition->deliveryCost,
                total: $composition->total,
            ));
        });

        $this->foodOrderCustomerNotifier->notifyCompositionChanged($order);

        return $order;
    }

    /**
     * dish_id из текущего items_snapshot — их можно оставлять при правке состава без is_available.
     *
     * @param  list<array<string, mixed>>  $itemsSnapshot
     * @return list<int>
     */
    private function existingDishIdsFromSnapshot(array $itemsSnapshot): array
    {
        $ids = [];

        foreach ($itemsSnapshot as $line) {
            if (! is_array($line) || ! isset($line['dish_id'])) {
                continue;
            }

            $ids[] = (int) $line['dish_id'];
        }

        return array_values(array_unique($ids));
    }
}
