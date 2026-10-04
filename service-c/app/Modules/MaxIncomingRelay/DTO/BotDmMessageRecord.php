<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\DTO;

use App\Modules\MaxIncomingRelay\Enums\BotDmAuthorType;

/**
 * Доменная проекция сообщения лички с ботом без Eloquent.
 */
readonly class BotDmMessageRecord
{
    public function __construct(
        public int $id,
        public int $maxUserId,
        public int $senderMaxUserId,
        public BotDmAuthorType $authorType,
        public string $body,
        public ?int $chatId,
        public string $createdAt,
        public ?string $senderFirstName = null,
        public ?string $senderLastName = null,
        public ?string $senderUsername = null,
    ) {}
}
