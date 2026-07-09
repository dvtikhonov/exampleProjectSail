<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Известные получатели UI Stand из webhook (bot_started, message_callback).
 * Нужны для отправки «тест бот 2» в тот же чат/диалог, где отвечает «Вы нажали кнопку: …».
 */
final class MaxUiStandRecipientRegistry
{
    private const CACHE_KEY = 'max.ui_stand.known_recipients';

    private const TTL_SECONDS = 2_592_000;

    private const MAX_RECIPIENTS = 50;

    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    public function rememberChatId(int $chatId): void
    {
        if ($chatId === 0) {
            return;
        }

        $recipients = $this->read();
        $recipients['chat_ids'] = $this->prependUniqueId($recipients['chat_ids'], $chatId);
        $this->write($recipients);
    }

    public function rememberUserId(int $userId): void
    {
        if ($userId === 0) {
            return;
        }

        $recipients = $this->read();
        $recipients['user_ids'] = $this->prependUniqueId($recipients['user_ids'], $userId);
        $this->write($recipients);
    }

    /**
     * @return list<int>
     */
    public function chatIds(): array
    {
        return $this->read()['chat_ids'];
    }

    /**
     * @return list<int>
     */
    public function userIds(): array
    {
        return $this->read()['user_ids'];
    }

    /**
     * @return array{chat_ids: list<int>, user_ids: list<int>}
     */
    private function read(): array
    {
        $stored = $this->cache->get(self::CACHE_KEY);

        if (! is_array($stored)) {
            return [
                'chat_ids' => [],
                'user_ids' => [],
            ];
        }

        return [
            'chat_ids' => $this->normalizeIds($stored['chat_ids'] ?? []),
            'user_ids' => $this->normalizeIds($stored['user_ids'] ?? []),
        ];
    }

    /**
     * @param  array{chat_ids: list<int>, user_ids: list<int>}  $recipients
     */
    private function write(array $recipients): void
    {
        $this->cache->put(self::CACHE_KEY, [
            'chat_ids' => array_slice($recipients['chat_ids'], 0, self::MAX_RECIPIENTS),
            'user_ids' => array_slice($recipients['user_ids'], 0, self::MAX_RECIPIENTS),
        ], self::TTL_SECONDS);
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function prependUniqueId(array $ids, int $id): array
    {
        $filtered = array_values(array_filter(
            $ids,
            static fn (int $existingId): bool => $existingId !== $id,
        ));

        array_unshift($filtered, $id);

        return $filtered;
    }

    /**
     * @param  mixed  $ids
     * @return list<int>
     */
    private function normalizeIds(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map(intval(...), $ids)));
    }
}
