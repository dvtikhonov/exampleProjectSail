<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderAdminRepositoryInterface;
use App\Enums\Food\FoodOrderAdminRole;
use App\Enums\Food\OrderReviewStatus;
use App\Enums\Food\OrderStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Models\FoodOrder;
use App\Models\MaxUser;

/**
 * Проверка прав администратора и допустимости перехода статуса проверки заказа.
 */
class OrderReviewAuthorizationService
{
    public function __construct(
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdminRepository,
    ) {}

    /**
     * @throws FoodDomainException
     */
    public function assertCanApproveAddress(MaxUser $admin, FoodOrder $order): void
    {
        $this->assertHasRole($admin, FoodOrderAdminRole::AddressReviewer);
        $this->assertAddressReviewPending($order);
    }

    /**
     * @throws FoodDomainException
     */
    public function assertCanRejectAddress(MaxUser $admin, FoodOrder $order, string $comment): void
    {
        $this->assertCanApproveAddress($admin, $order);
        $this->assertRejectionCommentPresent($comment);
    }

    /**
     * @throws FoodDomainException
     */
    public function assertCanApproveComposition(MaxUser $admin, FoodOrder $order): void
    {
        $this->assertHasRole($admin, FoodOrderAdminRole::CompositionReviewer);
        $this->assertCompositionReviewPending($order);
    }

    /**
     * @throws FoodDomainException
     */
    public function assertCanRejectComposition(MaxUser $admin, FoodOrder $order, string $comment): void
    {
        $this->assertCanApproveComposition($admin, $order);
        $this->assertRejectionCommentPresent($comment);
    }

    /**
     * @throws FoodDomainException
     */
    private function assertHasRole(MaxUser $admin, FoodOrderAdminRole $role): void
    {
        if (! $this->foodOrderAdminRepository->hasActiveRole($admin->max_user_id, $role)) {
            throw new FoodDomainException('Forbidden.', 403);
        }
    }

    /**
     * @throws FoodDomainException
     */
    private function assertAddressReviewPending(FoodOrder $order): void
    {
        if ($order->address_review_status !== OrderReviewStatus::Pending) {
            throw new FoodDomainException('Address review already completed.', 422);
        }

        if ($this->isReviewClosed($order->status)) {
            throw new FoodDomainException('Order is not awaiting address review.', 422);
        }
    }

    /**
     * @throws FoodDomainException
     */
    private function assertCompositionReviewPending(FoodOrder $order): void
    {
        if (! $order->isInCompositionReviewQueue()) {
            throw new FoodDomainException('Composition review already completed.', 422);
        }
    }

    private function isReviewClosed(OrderStatus $status): bool
    {
        return in_array($status, [OrderStatus::Rejected, OrderStatus::Confirmed], true);
    }

    /**
     * @throws FoodDomainException
     */
    private function assertRejectionCommentPresent(string $comment): void
    {
        if (trim($comment) === '') {
            throw new FoodDomainException('Rejection comment is required.', 422);
        }
    }
}
