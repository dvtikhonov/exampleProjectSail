<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderAdminRepositoryInterface;
use App\Contracts\Food\OrderCustomerNotifyRecipientResolverInterface;
use App\Enums\Food\FoodOrderAdminRole;
use App\Models\FoodOrder;
use Illuminate\Support\Facades\Log;

/**
 * Определяет получателей клиентских уведомлений: клиент или активные max_manager.
 */
final class OrderCustomerNotifyRecipientResolver implements OrderCustomerNotifyRecipientResolverInterface
{
    public function __construct(
        private readonly FoodOrderAdminRepositoryInterface $adminRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function resolveMaxUserIds(FoodOrder $order): array
    {
        if (! $order->is_manual) {
            return [(int) $order->max_user_id];
        }

        $managerIds = $this->adminRepository->listActiveMaxUserIdsByRole(FoodOrderAdminRole::MaxManager);

        if ($managerIds === []) {
            Log::channel('messMax')->warning('MAX manual order customer notification: no active max_manager recipients', [
                'order_id' => $order->id,
                'max_user_id' => $order->max_user_id,
            ]);
        }

        return $managerIds;
    }
}
