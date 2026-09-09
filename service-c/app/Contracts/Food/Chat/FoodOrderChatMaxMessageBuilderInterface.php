<?php

declare(strict_types=1);

namespace App\Contracts\Food\Chat;

use App\DTO\Food\Chat\OrderMessageDto;
use App\DTO\Food\Order\FoodOrderRecord;

/**
 * Сборка текстов MAX-уведомлений о сообщениях в чате заказа и deep-link параметров.
 */
interface FoodOrderChatMaxMessageBuilderInterface
{
    /**
     * Короткое уведомление клиенту о новом сообщении в чате заказа (без текста сообщения).
     */
    public function buildOrderChatCustomerNotification(FoodOrderRecord $order): string;

    /**
     * Уведомление в MAX_UI_STAND_* о новом сообщении в чате заказа (с текстом сообщения).
     */
    public function buildOrderChatUiStandNotification(FoodOrderRecord $order, OrderMessageDto $message): string;

    /**
     * Payload кнопки open_app → start_param mini-app (только [A-Za-z0-9_-]).
     *
     * @see https://dev.max.ru/docs/webapps/introduction
     */
    public function buildOrderChatStartParam(int $orderId): string;

    /**
     * URL mini-app с query deep-link (локальный браузер / fallback).
     */
    public function buildOrderChatOpenAppUrl(int $orderId, ?string $baseWebAppUrl): ?string;
}
