<?php

declare(strict_types=1);

namespace App\Services\Max\Food;

use App\Contracts\Food\Review\FoodOrderCustomerCompositionMaxMessageBuilderInterface;
use App\Contracts\Food\Review\FoodOrderCustomerMaxMessageBuilderInterface;
use App\Contracts\Food\Review\FoodOrderCustomerStatusMaxMessageBuilderInterface;
use App\Contracts\Food\Review\FoodOrderManualCreatorMaxMessageBuilderInterface;
use App\Contracts\Food\Review\FoodOrderUiStandNewRequestMaxMessageBuilderInterface;
use App\DTO\Food\Order\FoodOrderRecord;
use App\DTO\Food\Order\OrderDto;
use App\DTO\Food\Shared\MaxUserDisplayDto;
use App\Enums\Food\Review\OrderRejectionScope;
use App\Support\Max\Food\Formatting\FoodOrderMaxTextAssembler;

/**
 * Facade BC: тексты MAX-уведомлений о статусе/составе заказа (клиент и новая заявка в UI Stand).
 *
 * Реализует узкие порты через composition; для новых зависимостей предпочтительнее узкий Interface.
 */
class FoodOrderCustomerMaxMessageBuilder implements FoodOrderCustomerMaxMessageBuilderInterface
{
    public function __construct(
        private readonly FoodOrderUiStandNewRequestMaxMessageBuilderInterface $uiStandNewRequestBuilder,
        private readonly FoodOrderCustomerStatusMaxMessageBuilderInterface $customerStatusBuilder,
        private readonly FoodOrderCustomerCompositionMaxMessageBuilderInterface $compositionChangedBuilder,
        private readonly FoodOrderManualCreatorMaxMessageBuilderInterface $manualOrderCreatorConfirmedBuilder,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function build(
        OrderDto $order,
        MaxUserDisplayDto $customer,
        int $maxTextLength = FoodOrderMaxTextAssembler::DEFAULT_MAX_TEXT_LENGTH,
    ): string {
        return $this->uiStandNewRequestBuilder->build($order, $customer, $maxTextLength);
    }

    /**
     * {@inheritDoc}
     */
    public function buildCustomerSubmitted(FoodOrderRecord $order): string
    {
        return $this->customerStatusBuilder->buildCustomerSubmitted($order);
    }

    /**
     * {@inheritDoc}
     */
    public function buildCustomerConfirmed(FoodOrderRecord $order): string
    {
        return $this->customerStatusBuilder->buildCustomerConfirmed($order);
    }

    /**
     * {@inheritDoc}
     */
    public function buildManualOrderCreatorConfirmed(
        FoodOrderRecord $order,
        int $maxTextLength = FoodOrderMaxTextAssembler::DEFAULT_MAX_TEXT_LENGTH,
    ): string {
        return $this->manualOrderCreatorConfirmedBuilder->buildManualOrderCreatorConfirmed(
            $order,
            $maxTextLength,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function buildCustomerRejected(FoodOrderRecord $order, OrderRejectionScope $scope): string
    {
        return $this->customerStatusBuilder->buildCustomerRejected($order, $scope);
    }

    /**
     * {@inheritDoc}
     */
    public function buildCustomerCompositionChanged(
        FoodOrderRecord $order,
        int $maxTextLength = FoodOrderMaxTextAssembler::DEFAULT_MAX_TEXT_LENGTH,
    ): string {
        return $this->compositionChangedBuilder->buildCustomerCompositionChanged($order, $maxTextLength);
    }
}
