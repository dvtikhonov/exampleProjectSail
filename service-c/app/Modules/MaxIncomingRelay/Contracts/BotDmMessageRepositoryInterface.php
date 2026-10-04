<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Contracts;

use App\Modules\MaxIncomingRelay\DTO\BotDmMessageRecord;
use App\Modules\MaxIncomingRelay\Enums\BotDmAuthorType;

/**
 * Репозиторий сообщений лички пользователя с ботом.
 */
interface BotDmMessageRepositoryInterface
{
    /**
     * Создаёт сообщение в истории лички.
     */
    public function create(
        int $maxUserId,
        int $senderMaxUserId,
        BotDmAuthorType $authorType,
        string $body,
        ?int $chatId = null,
    ): BotDmMessageRecord;

    /**
     * Сообщения пользователя в хронологическом порядке; при after_id — только с id больше.
     *
     * @return list<BotDmMessageRecord>
     */
    public function listForUser(int $maxUserId, ?int $afterId = null, int $limit = 50): array;

    /**
     * Находит сообщение по идентификатору.
     */
    public function findById(int $id): ?BotDmMessageRecord;
}
