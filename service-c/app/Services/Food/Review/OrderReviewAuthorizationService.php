<?php

declare(strict_types=1);

namespace App\Services\Food\Review;

use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Enums\Food\Review\OrderReviewStep;
use App\Exceptions\Food\FoodDomainException;

/**
 * Проверка прав администратора и допустимости перехода статуса проверки заказа.
 */
class OrderReviewAuthorizationService
{
    /**
     * Проверяет право администратора одобрить шаг.
     *
     * @throws FoodDomainException
     */
    public function assertCanApprove(MaxUserIdentity $admin, FoodOrderRecord $order, OrderReviewStep $step): void
    {
        $this->assertHasRole($admin, $step->requiredRole());
        $step->assertPending($order);
    }

    /**
     * Проверяет право администратора отклонить шаг.
     *
     * @throws FoodDomainException
     */
    public function assertCanReject(MaxUserIdentity $admin, FoodOrderRecord $order, OrderReviewStep $step, string $comment): void
    {
        $this->assertCanApprove($admin, $order, $step);
        $this->assertRejectionCommentPresent($comment);
    }

    /**
     * Проверяет право администратора редактировать состав заказа в очереди проверки.
     *
     * @throws FoodDomainException
     */
    public function assertCanEditComposition(MaxUserIdentity $admin, FoodOrderRecord $order): void
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
    private function assertHasRole(MaxUserIdentity $admin, FoodOrderAdminRole $role): void
    {
        if (! $admin->hasAdminRole($role)) {
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
