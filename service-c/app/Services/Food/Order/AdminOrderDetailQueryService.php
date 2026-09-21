<?php

declare(strict_types=1);

namespace App\Services\Food\Order;

use App\Contracts\Food\Order\AdminOrderDetailQueryServiceInterface;
use App\Contracts\Food\Order\FoodOrderAdminReviewReadRepositoryInterface;
use App\Contracts\Food\Shared\FoodMoneyFormatterInterface;
use App\DTO\Food\Order\AdminOrderDetailDto;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Enums\Food\Order\AdminOrderListScope;
use App\Exceptions\Food\FoodDomainException;

/**
 * Детальная выборка заказа для административного API проверки.
 */
class AdminOrderDetailQueryService implements AdminOrderDetailQueryServiceInterface
{
    public function __construct(
        private readonly FoodOrderAdminReviewReadRepositoryInterface $foodOrderReadRepository,
        private readonly FoodMoneyFormatterInterface $moneyFormatter,
    ) {}

    /**
     * Возвращает детальные данные заказа для админского API.
     *
     * @throws FoodDomainException
     */
    public function detail(
        MaxUserIdentity $admin,
        int $orderId,
        AdminOrderListScope $scope,
    ): AdminOrderDetailDto {
        $this->assertScopeAccess($admin, $scope);

        $order = $this->foodOrderReadRepository->findByIdForScope($orderId, $scope);

        if ($order === null) {
            throw new FoodDomainException('Заказ не найден.', 404);
        }

        return $this->mapDetail($order);
    }

    /**
     * Строит детальный DTO заказа по проекции (с перезагрузкой).
     *
     * @throws FoodDomainException
     */
    public function detailFromRecord(FoodOrderRecord $order): AdminOrderDetailDto
    {
        $order = $this->foodOrderReadRepository->findById($order->id);

        if ($order === null) {
            throw new FoodDomainException('Заказ не найден.', 404);
        }

        return $this->mapDetail($order);
    }

    /**
     * Проверяет доступ администратора к указанному scope проверки.
     *
     * @throws FoodDomainException
     */
    private function assertScopeAccess(MaxUserIdentity $admin, AdminOrderListScope $scope): void
    {
        if (! $admin->hasAdminRole($scope->requiredRole())) {
            throw new FoodDomainException('Доступ запрещён.', 403);
        }
    }

    /**
     * Преобразует заказ в детальный админский DTO.
     */
    private function mapDetail(FoodOrderRecord $order): AdminOrderDetailDto
    {
        return new AdminOrderDetailDto(
            id: $order->id,
            status: $order->status->value,
            restaurantId: $order->restaurantId,
            restaurantName: (string) ($order->restaurantName ?? ''),
            customerMaxUserId: $order->maxUserId,
            customerFirstName: $order->customerFirstName,
            customerLastName: $order->customerLastName,
            customerUsername: $order->customerUsername,
            deliveryAddress: $order->deliveryAddress,
            deliveryDate: $order->deliveryDate,
            itemsTotal: $this->formatMoney($order->itemsTotal),
            deliveryCost: $order->deliveryCost !== null ? $this->formatMoney($order->deliveryCost) : null,
            total: $this->formatMoney($order->total),
            itemsSnapshot: $order->itemsSnapshot,
            addressReviewStatus: $order->addressReviewStatus->value,
            compositionReviewStatus: $order->compositionReviewStatus->value,
            paymentReviewStatus: $order->paymentReviewStatus->value,
            addressReviewedBy: $order->addressReviewedBy,
            addressReviewedAt: $order->addressReviewedAt,
            addressRejectionComment: $order->addressRejectionComment,
            compositionReviewedBy: $order->compositionReviewedBy,
            compositionReviewedAt: $order->compositionReviewedAt,
            compositionRejectionComment: $order->compositionRejectionComment,
            paymentReviewedBy: $order->paymentReviewedBy,
            paymentReviewedAt: $order->paymentReviewedAt,
            paymentRejectionComment: $order->paymentRejectionComment,
            createdAt: $order->createdAt,
        );
    }

    /**
     * Форматирует денежную сумму.
     */
    private function formatMoney(mixed $value): string
    {
        return $this->moneyFormatter->format((float) $value);
    }
}
