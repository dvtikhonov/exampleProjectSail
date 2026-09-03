<?php

declare(strict_types=1);

namespace App\Mappers\Max;

use App\DTO\Food\Shared\MaxUserDisplayDto;
use App\DTO\Max\MaxUserRecord;

/**
 * Маппинг {@see MaxUserRecord} в {@see MaxUserDisplayDto}.
 */
class MaxUserDisplayMapper
{
    /**
     * Преобразует доменную проекцию пользователя MAX в DTO для текста уведомлений.
     */
    public function fromRecord(MaxUserRecord $user): MaxUserDisplayDto
    {
        return new MaxUserDisplayDto(
            maxUserId: $user->maxUserId,
            firstName: $user->firstName,
            lastName: $user->lastName,
            username: $user->username,
        );
    }
}
