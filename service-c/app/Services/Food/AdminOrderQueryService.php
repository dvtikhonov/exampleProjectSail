<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderAdminRepositoryInterface;
use App\Contracts\Food\FoodOrderRepositoryInterface;
use App\DTO\Food\AdminOrderDetailDto;
use App\DTO\Food\AdminOrderListItemDto;
use App\Enums\Food\FoodOrderAdminRole;
use App\Enums\Food\OrderReviewStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Models\FoodOrder;
use App\Models\MaxUser;

/**
 * Выборка заказов для административного API проверки.
 */
class AdminOrderQueryService
{
    public function __construct(
        private readonly FoodOrderRepositoryInterface $foodOrderRepository,
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdminRepository,
        private readonly FoodMoneyFormatter $moneyFormatter,
    ) {}

    /**
     * @return list<string>
     */
    public function activeRoleValues(MaxUser $admin): array
    {
        return array_map(
            static fn (FoodOrderAdminRole $role): string => $role->value,
            $this->foodOrderAdminRepository->getActiveRoles($admin->max_user_id),
        );
    }

    /**
     * @return list<AdminOrderListItemDto>
     *
     * @throws FoodDomainException
     */
    public function listPending(MaxUser $admin, string $scope): array
    {
        $this->assertScopeAccess($admin, $scope);

        $orders = match ($scope) {
            'address' => $this->foodOrderRepository->findForAddressReview(OrderReviewStatus::Pending),
            'composition' => $this->foodOrderRepository->findForCompositionReview(OrderReviewStatus::Pending),
            default => throw new FoodDomainException('Invalid scope. Use address or composition.', 422),
        };

        return array_map(
            fn (FoodOrder $order): AdminOrderListItemDto => $this->mapListItem($order),
            $orders,
        );
    }

    /**
     * @throws FoodDomainException
     */
    public function detail(MaxUser $admin, int $orderId, string $scope): AdminOrderDetailDto
    {
        $this->assertScopeAccess($admin, $scope);

        $order = $this->foodOrderRepository->findById($orderId);

        if ($order === null) {
            throw new FoodDomainException('Order not found.', 404);
        }

        $this->assertOrderVisibleForScope($order, $scope);

        return $this->mapDetail($order);
    }

    /**
     * @throws FoodDomainException
     */
    public function detailFromModel(FoodOrder $order): AdminOrderDetailDto
    {
        $order = $this->foodOrderRepository->findById($order->id);

        if ($order === null) {
            throw new FoodDomainException('Order not found.', 404);
        }

        return $this->mapDetail($order);
    }

    /**
     * @throws FoodDomainException
     */
    private function assertScopeAccess(MaxUser $admin, string $scope): void
    {
        $role = $this->resolveScopeRole($scope);

        if (! $this->foodOrderAdminRepository->hasActiveRole($admin->max_user_id, $role)) {
            throw new FoodDomainException('Forbidden.', 403);
        }
    }

    /**
     * @throws FoodDomainException
     */
    private function assertOrderVisibleForScope(FoodOrder $order, string $scope): void
    {
        match ($scope) {
            'address' => $this->assertAddressQueueOrder($order),
            'composition' => $this->assertCompositionQueueOrder($order),
            default => throw new FoodDomainException('Invalid scope. Use address or composition.', 422),
        };
    }

    /**
     * @throws FoodDomainException
     */
    private function assertAddressQueueOrder(FoodOrder $order): void
    {
        if ($order->address_review_status !== OrderReviewStatus::Pending) {
            throw new FoodDomainException('Order is not in address review queue.', 422);
        }
    }

    /**
     * @throws FoodDomainException
     */
    private function assertCompositionQueueOrder(FoodOrder $order): void
    {
        if (! $order->isInCompositionReviewQueue()) {
            throw new FoodDomainException('Order is not in composition review queue.', 422);
        }
    }

    /**
     * @throws FoodDomainException
     */
    private function resolveScopeRole(string $scope): FoodOrderAdminRole
    {
        return match ($scope) {
            'address' => FoodOrderAdminRole::AddressReviewer,
            'composition' => FoodOrderAdminRole::CompositionReviewer,
            default => throw new FoodDomainException('Invalid scope. Use address or composition.', 422),
        };
    }

    private function mapListItem(FoodOrder $order): AdminOrderListItemDto
    {
        return new AdminOrderListItemDto(
            id: $order->id,
            status: $order->status->value,
            restaurantId: $order->restaurant_id,
            restaurantName: (string) $order->restaurant?->name,
            customerMaxUserId: $order->max_user_id,
            customerFirstName: $order->maxUser?->first_name,
            customerLastName: $order->maxUser?->last_name,
            customerUsername: $order->maxUser?->username,
            deliveryAddress: $order->delivery_address,
            itemsTotal: $this->formatMoney($order->items_total),
            deliveryCost: $order->delivery_cost !== null ? $this->formatMoney($order->delivery_cost) : null,
            total: $this->formatMoney($order->total),
            addressReviewStatus: $order->address_review_status->value,
            compositionReviewStatus: $order->composition_review_status->value,
            createdAt: $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
        );
    }

    private function mapDetail(FoodOrder $order): AdminOrderDetailDto
    {
        return new AdminOrderDetailDto(
            id: $order->id,
            status: $order->status->value,
            restaurantId: $order->restaurant_id,
            restaurantName: (string) $order->restaurant?->name,
            customerMaxUserId: $order->max_user_id,
            customerFirstName: $order->maxUser?->first_name,
            customerLastName: $order->maxUser?->last_name,
            customerUsername: $order->maxUser?->username,
            deliveryAddress: $order->delivery_address,
            itemsTotal: $this->formatMoney($order->items_total),
            deliveryCost: $order->delivery_cost !== null ? $this->formatMoney($order->delivery_cost) : null,
            total: $this->formatMoney($order->total),
            itemsSnapshot: $order->items_snapshot ?? [],
            addressReviewStatus: $order->address_review_status->value,
            compositionReviewStatus: $order->composition_review_status->value,
            addressReviewedBy: $order->address_reviewed_by,
            addressReviewedAt: $order->address_reviewed_at?->toIso8601String(),
            addressRejectionComment: $order->address_rejection_comment,
            compositionReviewedBy: $order->composition_reviewed_by,
            compositionReviewedAt: $order->composition_reviewed_at?->toIso8601String(),
            compositionRejectionComment: $order->composition_rejection_comment,
            createdAt: $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
        );
    }

    private function formatMoney(mixed $value): string
    {
        return $this->moneyFormatter->format((float) $value);
    }
}
