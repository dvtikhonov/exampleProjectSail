<?php

declare(strict_types=1);

namespace App\DTO\Max;

use App\Enums\MessengerChannel;

/**
 * Канал и внешний id пользователя для доставки уведомлений в мессенджер.
 */
readonly class MessengerUserIdentity
{
    public function __construct(
        public int $maxUserId,
        public MessengerChannel $messenger,
        public int $externalUserId,
    ) {}
}
