<?php

declare(strict_types=1);

namespace App\Services\Max\Food;

use App\Contracts\Food\Chat\FoodOrderChatMaxMessageBuilderInterface;
use App\DTO\Food\Chat\OrderMessageDto;
use App\DTO\Food\Order\FoodOrderRecord;
use App\Support\Max\Food\Formatting\FoodOrderMaxTextAssembler;

/**
 * Тексты MAX-уведомлений о сообщениях в чате заказа и deep-link параметры.
 */
class FoodOrderChatMaxMessageBuilder implements FoodOrderChatMaxMessageBuilderInterface
{
    public function __construct(
        private readonly FoodOrderMaxTextAssembler $textAssembler,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function buildOrderChatCustomerNotification(FoodOrderRecord $order): string
    {
        return sprintf('В чат заказа №%d поступило сообщение', $order->id);
    }

    /**
     * {@inheritDoc}
     */
    public function buildOrderChatUiStandNotification(FoodOrderRecord $order, OrderMessageDto $message): string
    {
        return implode("\n", [
            sprintf('В чат заказа №%d поступило сообщение', $order->id),
            $this->textAssembler->truncateChatPreview($message->body),
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function buildOrderChatStartParam(int $orderId): string
    {
        return sprintf('order_%d_chat', $orderId);
    }

    /**
     * {@inheritDoc}
     */
    public function buildOrderChatOpenAppUrl(int $orderId, ?string $baseWebAppUrl): ?string
    {
        $baseUrl = trim((string) $baseWebAppUrl);

        if ($baseUrl === '') {
            return null;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.http_build_query([
            'order_id' => $orderId,
            'view' => 'chat',
        ]);
    }
}
