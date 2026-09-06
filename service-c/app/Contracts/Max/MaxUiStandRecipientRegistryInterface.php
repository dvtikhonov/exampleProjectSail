<?php

declare(strict_types=1);

namespace App\Contracts\Max;

/**
 * Известные получатели UI Stand из webhook (bot_started, message_callback).
 */
interface MaxUiStandRecipientRegistryInterface
{
    /**
     * Запоминает chat_id из webhook для последующих тестовых рассылок.
     */
    public function rememberChatId(int $chatId): void;

    /**
     * Запоминает user_id из webhook для последующих тестовых рассылок.
     */
    public function rememberUserId(int $userId): void;

    /**
     * Известные chat_id получателей UI Stand.
     *
     * @return list<int>
     */
    public function chatIds(): array;

    /**
     * Известные user_id получателей UI Stand.
     *
     * @return list<int>
     */
    public function userIds(): array;
}
