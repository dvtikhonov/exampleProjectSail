<?php

declare(strict_types=1);

namespace App\Services\Food\Review;

use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Enums\Food\Review\OrderReviewStep;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Food\FoodOrder;
use App\Models\Max\MaxUser;

/**
 * Проверка прав администратора и допустимости перехода статуса проверки заказа.
 */
class OrderReviewAuthorizationService
{
    public function __construct(
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdminRepository,
    ) {}

    /**
     * Проверяет право администратора одобрить шаг.
     *
     * @throws FoodDomainException
     */
    public function assertCanApprove(MaxUser $admin, FoodOrder $order, OrderReviewStep $step): void
    {
        $this->assertHasRole($admin, $step->requiredRole());
        $step->assertPending($order);
    }

    /**
     * Проверяет право администратора отклонить шаг.
     *
     * @throws FoodDomainException
     */
    public function assertCanReject(MaxUser $admin, FoodOrder $order, OrderReviewStep $step, string $comment): void
    {
        $this->assertCanApprove($admin, $order, $step);
        $this->assertRejectionCommentPresent($comment);
    }

    /**
     * Проверяет право администратора редактировать состав заказа в очереди проверки.
     *
     * @throws FoodDomainException
     */
    public function assertCanEditComposition(MaxUser $admin, FoodOrder $order): void
    {
        $this->assertHasRole($admin, FoodOrderAdminRole::CompositionReviewer);

        if (! $order->isInCompositionReviewQueue()) {
            throw new FoodDomainException('Проверка состава уже завершена.', 422);
        }
    }

    /**
     * Проверяет наличие активной роли у администратора.
     *
     * @throws FoodDomainException
     */
    private function assertHasRole(MaxUser $admin, FoodOrderAdminRole $role): void
    {
        if (! $this->foodOrderAdminRepository->hasActiveRole($admin->max_user_id, $role)) {
            throw new FoodDomainException('Доступ запрещён.', 403);
        }
    }

    /**
     * Проверяет, что комментарий отклонения заполнен.
     *
     * @throws FoodDomainException
     */
    private function assertRejectionCommentPresent(string $comment): void
    {
        if (trim($comment) === '') {
            throw new FoodDomainException('Комментарий при отклонении обязателен.', 422);
        }
    }
}
