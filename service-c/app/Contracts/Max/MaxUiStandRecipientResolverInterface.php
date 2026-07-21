<?php

declare(strict_types=1);

namespace App\Contracts\Max;

/**
 * Резолвер получателей UI Stand (MAX_UI_STAND_* и кэш webhook).
 */
interface MaxUiStandRecipientResolverInterface
{
    /**
     * Получатели для рассылки из .env (max:ui-stand:send).
     *
     * @return list<int>
     */
    public function configuredChatIds(): array;

    /**
     * Возвращает настроенные MAX user id получателей UI-стенда.
     *
     * @return list<int>
     */
    public function configuredUserIds(): array;

    /**
     * Все известные chat_id: .env + чаты из callback в MAX.
     *
     * @return list<int>
     */
    public function chatIds(): array;

    /**
     * Все известные user_id: .env + пользователи из bot_started / личных диалогов.
     *
     * @return list<int>
     */
    public function userIds(): array;
}
