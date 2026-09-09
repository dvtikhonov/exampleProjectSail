<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\OrderDto;
use App\DTO\Food\Shared\MaxUserDisplayDto;
use App\Enums\Food\Review\OrderRejectionScope;

/**
 * Сборка текстов MAX-уведомлений о статусе/составе заказа (клиент и UI Stand заявка).
 */
interface FoodOrderCustomerMaxMessageBuilderInterface
{
    /**
     * Собирает текст уведомления о новой заявке с учётом лимита символов.
     */
    public function build(
        OrderDto $order,
        MaxUserDisplayDto $customer,
        int $maxTextLength = 4000,
    ): string;

    /**
     * Текст уведомления клиенту о принятии заказа на рассмотрение.
     */
    public function buildCustomerSubmitted(FoodOrderRecord $order): string;

    /**
     * Текст уведомления клиенту о подтверждении заявки.
     */
    public function buildCustomerConfirmed(FoodOrderRecord $order): string;

    /**
     * Текст доп. уведомления менеджеру, оформившему ручной заказ, после подтверждения.
     */
    public function buildManualOrderCreatorConfirmed(
        FoodOrderRecord $order,
        int $maxTextLength = 4000,
    ): string;

    /**
     * Текст уведомления клиенту об отклонении заявки.
     */
    public function buildCustomerRejected(FoodOrderRecord $order, OrderRejectionScope $scope): string;

    /**
     * Текст уведомления клиенту об окончательном варианте заказа после правки состава.
     */
    public function buildCustomerCompositionChanged(
        FoodOrderRecord $order,
        int $maxTextLength = 4000,
    ): string;
}
