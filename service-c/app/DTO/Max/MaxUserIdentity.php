<?php

declare(strict_types=1);

namespace App\DTO\Max;

/**
 * Идентичность пользователя MAX для Max-сервисов без Eloquent.
 */
readonly class MaxUserIdentity
{
    public function __construct(
        public int $maxUserId,
    ) {}
}
