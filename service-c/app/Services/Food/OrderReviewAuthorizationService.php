<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\FoodOrderAdminRepositoryInterface;
use App\Enums\Food\FoodOrderAdminRole;
use App\Enums\Food\OrderReviewStep;
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
    public function assertCanApprove(MaxUser $admin, FoodOrder $order, OrderReviewStep $step): void
    {
        $this->assertHasRole($admin, $step->requiredRole());
        $step->assertPending($order);
    }

    /**
     * @throws FoodDomainException
     */
    public function assertCanReject(MaxUser $admin, FoodOrder $order, OrderReviewStep $step, string $comment): void
    {
        $this->assertCanApprove($admin, $order, $step);
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
    private function assertRejectionCommentPresent(string $comment): void
    {
        if (trim($comment) === '') {
            throw new FoodDomainException('Rejection comment is required.', 422);
        }
    }
}
