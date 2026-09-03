<?php

declare(strict_types=1);

namespace App\Services\Food\Review;

use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Contracts\Food\Review\OrderCustomerNotifyRecipientResolverInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Enums\Food\Review\FoodOrderAdminRole;
use Psr\Log\LoggerInterface;

/**
 * Определяет получателей клиентских уведомлений: клиент или активные max_manager.
 */
final class OrderCustomerNotifyRecipientResolver implements OrderCustomerNotifyRecipientResolverInterface
{
    public function __construct(
        private readonly FoodOrderAdminRepositoryInterface $adminRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function resolveMaxUserIds(FoodOrderRecord $order): array
    {
        if (! $order->isManual) {
            return [$order->maxUserId];
        }

        $managerIds = $this->adminRepository->listActiveMaxUserIdsByRole(FoodOrderAdminRole::MaxManager);

        if ($managerIds === []) {
            $this->logger->warning('MAX manual order customer notification: no active max_manager recipients', [
                'order_id' => $order->id,
                'max_user_id' => $order->maxUserId,
            ]);
        }

        return $managerIds;
    }
}
