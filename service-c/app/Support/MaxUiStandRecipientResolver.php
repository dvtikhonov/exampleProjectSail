<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Max\MaxUiStandRecipientResolverInterface;
use Illuminate\Contracts\Config\Repository;

/**
 * Получатели UI Stand из .env и из webhook-событий (активные чаты/диалоги).
 */
final class MaxUiStandRecipientResolver implements MaxUiStandRecipientResolverInterface
{
    public function __construct(
        private readonly Repository $config,
        private readonly MaxUiStandRecipientRegistry $recipientRegistry,
    ) {}

    /**
     * Получатели для рассылки из .env (max:ui-stand:send).
     *
     * @return list<int>
     */
    public function configuredChatIds(): array
    {
        return $this->normalizeIds(
            (array) $this->config->get('max.ui_stand.recipient_chat_ids', []),
        );
    }

    /**
     * Возвращает настроенные MAX user id получателей UI-стенда.
     *
     * @return list<int>
     */
    public function configuredUserIds(): array
    {
        return $this->normalizeIds(
            (array) $this->config->get('max.ui_stand.recipient_user_ids', []),
        );
    }

    /**
     * Все известные chat_id: .env + чаты из callback в MAX.
     *
     * @return list<int>
     */
    public function chatIds(): array
    {
        return $this->uniqueIds([
            ...$this->configuredChatIds(),
            ...$this->recipientRegistry->chatIds(),
        ]);
    }

    /**
     * Все известные user_id: .env + пользователи из bot_started / личных диалогов.
     *
     * @return list<int>
     */
    public function userIds(): array
    {
        return $this->uniqueIds([
            ...$this->configuredUserIds(),
            ...$this->recipientRegistry->userIds(),
        ]);
    }

    /**
     * Нормализует список идентификаторов пользователей.
     *
     * @param  array<int|string, int|string>  $ids
     * @return list<int>
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_filter(array_map(intval(...), $ids)));
    }

    /**
     * Убирает дубликаты идентификаторов с сохранением порядка.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function uniqueIds(array $ids): array
    {
        return array_values(array_unique($ids));
    }
}
