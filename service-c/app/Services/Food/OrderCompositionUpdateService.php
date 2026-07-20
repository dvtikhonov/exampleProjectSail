<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderCustomerNotifierInterface;
use App\Contracts\Food\FoodOrderWriteRepositoryInterface;
use App\Contracts\Food\OrderCompositionSnapshotBuilderInterface;
use App\Contracts\Food\OrderCompositionUpdateServiceInterface;
use App\Exceptions\Food\FoodDomainException;
use App\Models\FoodOrder;
use App\Models\MaxUser;
use Illuminate\Support\Facades\DB;

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
    ) {}

    /**
     * {@inheritDoc}
     */
    public function update(int $orderId, MaxUser $admin, array $items): FoodOrder
    {
        $order = DB::transaction(function () use ($orderId, $admin, $items): FoodOrder {
            $order = $this->foodOrderWriteRepository->findByIdForUpdate($orderId);

            if ($order === null) {
                throw new FoodDomainException('Order not found.', 404);
            }

            $this->orderReviewAuthorizationService->assertCanEditComposition($admin, $order);

            $order->loadMissing('maxUser');
            $customer = $order->maxUser;

            if ($customer === null) {
                throw new FoodDomainException('Order customer not found.', 422);
            }

            $composition = $this->orderCompositionSnapshotBuilder->build(
                restaurantId: (int) $order->restaurant_id,
                customer: $customer,
                items: $items,
                existingDishIds: $this->existingDishIdsFromSnapshot($order->items_snapshot ?? []),
            );

            return $this->foodOrderWriteRepository->update($order, [
                'items_snapshot' => $composition->itemsSnapshot,
                'items_total' => $composition->itemsTotal,
                'delivery_cost' => $composition->deliveryCost,
                'total' => $composition->total,
            ]);
        });

        $this->foodOrderCustomerNotifier->notifyCompositionChanged($order);

        return $order;
    }

    /**
     * dish_id из текущего items_snapshot — их можно оставлять при правке состава без is_available.
     *
     * @param  list<array<string, mixed>>|array<int, mixed>  $itemsSnapshot
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
