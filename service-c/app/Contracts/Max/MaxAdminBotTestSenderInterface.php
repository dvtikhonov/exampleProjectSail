<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\MaxAdminBotTestSendResultDto;

/**
 * Отправка тестового сообщения администратору меню через MAX-бота.
 */
interface MaxAdminBotTestSenderInterface
{
    /**
     * Отправляет тестовое сообщение получателям из настроек уведомлений о заказах.
     *
     * @throws \App\Exceptions\Food\FoodDomainException при отсутствии настроек бота или получателей
     */
    public function sendTestMessage(): MaxAdminBotTestSendResultDto;

    /**
     * Отправляет тестовое сообщение «тест бот 2» во все чаты из MAX_UI_STAND_CHAT_IDS.
     *
     * @throws \App\Exceptions\Food\FoodDomainException при отсутствии настроек бота или получателей
     */
    public function sendUiStandTestMessage(): MaxAdminBotTestSendResultDto;
}
